<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\Console;

use Kdyby;
use Nette;
use Nette\Utils\Finder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Kdyby\Translation\MessageCatalogue;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtractCommand extends Command
{

	const ONESKY_DEFAULT_LANG = 'cs';

	/**
	 * @var string
	 */
	public $defaultOutputDir = '%appDir%/lang';

	/**
	 * @var Kdyby\Translation\Translator
	 */
	private $translator;

	/**
	 * @var \Kdyby\Translation\TranslationLoader
	 */
	private $loader;

	/**
	 * @var \Symfony\Component\Translation\Writer\TranslationWriter
	 */
	private $writer;

	/**
	 * @var \Symfony\Component\Translation\Extractor\ChainExtractor
	 */
	private $extractor;

	/**
	 * @var Nette\DI\Container
	 */
	private $serviceLocator;

	/**
	 * @var string
	 */
	private $outputFormat;

	/**
	 * @var array
	 */
	private $scanDirs;

	/**
	 * @var array
	 */
	private $excludedPrefixes;

	/**
	 * @var array
	 */
	private $excludePrefixFile;

	/**
	 * @var string
	 */
	private $outputDir;



	protected function configure()
	{
		$this->setName('kdyby:translation-extract')
			->setDescription('Extracts strings from application to translation files')
			->addOption('scan-dir', 'd', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The directory to parse the translations. Can contain %placeholders%.", array('%appDir%'))
			->addOption('output-format', 'f', InputOption::VALUE_REQUIRED, "Format name of the messages.")
			->addOption('output-dir', 'o', InputOption::VALUE_OPTIONAL, "Directory to write the messages to. Can contain %placeholders%.", $this->defaultOutputDir)
			->addOption('catalogue-language', 'l', InputOption::VALUE_OPTIONAL, "The language of the catalogue", 'en_US')
			->addOption('exclude-prefix', 'e', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The prefix(es) to exclude from extract.")
			->addOption('exclude-prefix-file', 'ef', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The file(s) with prefixes to exclude from extract.")
			->addOption('onesky-download', 'od', InputOption::VALUE_NONE, "Download translations from OneSky.")
			->addOption('onesky-upload', 'ou', InputOption::VALUE_NONE, "Upload translations to OneSky.");
			// todo: append
	}



	protected function initialize(InputInterface $input, OutputInterface $output)
	{
		$this->translator = $this->getHelper('container')->getByType('Kdyby\Translation\Translator');
		$this->loader = $this->getHelper('container')->getByType('Kdyby\Translation\TranslationLoader');
		$this->writer = $this->getHelper('container')->getByType('Symfony\Component\Translation\Writer\TranslationWriter');
		$this->extractor = $this->getHelper('container')->getByType('Symfony\Component\Translation\Extractor\ChainExtractor');
		$this->serviceLocator = $this->getHelper('container')->getContainer();
	}



	protected function validate(InputInterface $input, OutputInterface $output)
	{
		if (!in_array($this->outputFormat = trim($input->getOption('output-format'), '='), $formats = $this->writer->getFormats(), TRUE)) {
			$output->writeln('<error>Unknown --output-format</error>');
			$output->writeln(sprintf("<info>Choose one of: %s</info>", implode(', ', $formats)));

			return FALSE;
		}

		if (($input->getOption('onesky-download') || $input->getOption('onesky-upload')) && empty($this->serviceLocator->parameters['oneSky'])) {
			$output->writeln('<error>Please specify OneSky parameters in config first.</error>');

			return FALSE;
		}

		if ($input->getOption('onesky-download') && $input->getOption('onesky-upload')) {
			$output->writeln('<error>Specify maximally one from the following options: onesky-download, onesky-upload.</error>');

			return FALSE;
		}

		$this->scanDirs = $this->serviceLocator->expand($input->getOption('scan-dir'));
		foreach ($this->scanDirs as $dir) {
			if (!is_dir($dir)) {
				$output->writeln(sprintf('<error>Given --scan-dir "%s" does not exists.</error>', $dir));

				return FALSE;
			}
		}

		if (!is_dir($this->outputDir = $this->serviceLocator->expand($input->getOption('output-dir'))) || !is_writable($this->outputDir)) {
			$output->writeln(sprintf('<error>Given --output-dir "%s" does not exists or is not writable.</error>', $this->outputDir));

			return FALSE;
		}

		$this->excludedPrefixes = $this->serviceLocator->expand($input->getOption('exclude-prefix'));

		$this->excludePrefixFile = $this->serviceLocator->expand($input->getOption('exclude-prefix-file'));
		foreach ($this->excludePrefixFile as $file) {
			if (!is_file($file)) {
				$output->writeln(sprintf('<error>Given --exclude-prefix-file "%s" does not exists.</error>', $file));

				return FALSE;
			}
		}

		return TRUE;
	}



	protected function execute(InputInterface $input, OutputInterface $output)
	{
		if ($this->validate($input, $output) !== TRUE) {
			return 1;
		}

		foreach ($this->excludePrefixFile as $file) {
			$this->excludedPrefixes += preg_split('/\s+/', trim(file_get_contents($file)));
		}

		if (! $input->getOption('onesky-download') && ! $input->getOption('onesky-upload')) {
			// extrahovat překlady ze zdrojáků do češtiny, ale zobrazit pouze nepřeložené

			// překlady ze zdrojových kódů
			$catalogue = new MessageCatalogue($input->getOption('catalogue-language'));
			foreach ($this->scanDirs as $dir) {
				$output->writeln(sprintf('<info>Extracting %s</info>', $dir));
				$this->extractor->extract($dir, $catalogue);
			}
			$this->excludePrefixes($catalogue, $this->excludedPrefixes, FALSE);

			// překlady ze souborů v app/lang
			$existingCatalogue = new MessageCatalogue($input->getOption('catalogue-language'));
			$this->loader->loadMessages($this->outputDir, $existingCatalogue);
			$this->excludePrefixes($existingCatalogue, $this->excludedPrefixes);
			$catalogue->addCatalogue($existingCatalogue);

			// zobraz pouze prázdné překlady k přeložení
			$this->filterTranslations($catalogue, TRUE);

			$this->writer->writeTranslations($catalogue, $this->outputFormat, array(
				'path' => $this->outputDir,
			));

			$output->writeln(sprintf('<info>Catalogue was written to %s</info>', $this->outputDir));

		} else if ($input->getOption('onesky-download') || $input->getOption('onesky-upload')) {

			// OneSky

			if ($input->getOption('onesky-download')) {
				$oneSkyDir = static::tempnamDir(sys_get_temp_dir(), 'kdyby');	// temp dir
			}
			$lang = $input->getOption('catalogue-language');

			require 'Onesky_Api.php';
			$oneSky = new \Onesky_Api();
			$oneSkyParameters = $this->serviceLocator->parameters['oneSky'];
			$oneSky->setApiKey($oneSkyParameters['apiKey'])->setSecret($oneSkyParameters['apiSecret']);

			foreach (Finder::findFiles('*'. $lang .'.'. $input->getOption('output-format'))->in($this->outputDir) as $file) {
				// všechny soubory s překlady daného jazyka

				$matches = array();
				preg_match('/^(.+)\.(.+)\.(.+)$/', $file->getFilename(), $matches);
				$filename = $matches[1];
				$locale = $matches[2];
				$extension = $matches[3];

				/*
				$response = $oneSky->files('list', array(
					'project_id' => 33906,
				));
				print_r(json_decode($response, true));
				die();
				*/

				if ($locale === self::ONESKY_DEFAULT_LANG) {
					$uploadFilePathName = $file->getPath() .'/'. $filename .'.'. $extension;
					copy($file->getRealPath(), $uploadFilePathName);
				} else {
					$uploadFilePathName = $file->getRealPath();
				}

				// upload existujících překladů na server
				if ($input->getOption('onesky-upload')) {
					$output->writeln(sprintf('<info>Uploading \'%s\' catalogue to OneSkyApp</info>', $lang));

					$response = $oneSky->files('upload', array(
						'project_id' => $oneSkyParameters['projectId'],
						'file' => $uploadFilePathName,
						'file_format' => 'GNU_PO',
						'locale' => $locale,
					));
					print_r(json_decode($response, true));
				}

				// stažení existujících překladů ze serveru
				if ($input->getOption('onesky-download')) {
					$output->writeln(sprintf('<info>Downloading \'%s\' catalogue from OneSkyApp</info>', $lang));

					$response = $oneSky->translations('export', array(
						'project_id' => $oneSkyParameters['projectId'],
						'locale' => $locale,
						'source_file_name' => $filename .'.'. $extension,
						'export_file_name' => $file->getFilename(),
					));
					$decodedResponse = json_decode($response, true);
					if ($decodedResponse === NULL) {
						// v pořádku

						file_put_contents($oneSkyDir .'/'. $file->getFilename(), $response);
					} else {
						print_r($decodedResponse);
					}
				}

			}

			// stažení existujících překladů ze serveru
			if ($input->getOption('onesky-download')) {
				$oneSkyCatalogue = new MessageCatalogue($input->getOption('catalogue-language'));
				$this->loader->loadMessages($oneSkyDir, $oneSkyCatalogue);
				$this->excludePrefixes($oneSkyCatalogue, $this->excludedPrefixes);

				$this->writer->writeTranslations($oneSkyCatalogue, $this->outputFormat, array(
					'path' => $this->outputDir,
				));

				$output->writeln(sprintf('<info>Catalogue was written to %s</info>', $this->outputDir));

				static::rmdir_r($oneSkyDir);
			}

			if ($input->getOption('onesky-upload')) {
				$output->writeln(sprintf('<info>Catalogue has been uploaded to OneSkyApp</info>', $this->outputDir));
			}

		}

		return 0;
	}

	/**
	 * Ze vstupního katalogu smaže překlady s klíči v $excludedPrefixes.
	 * @param MessageCatalogue $catalogue
	 * @param array $excludedPrefixes
	 * @param boolean $onlyEmpty Smazat klíč pouze pokud je překlad prázdný?
	 */
	protected function excludePrefixes(MessageCatalogue &$catalogue, $excludedPrefixes, $onlyEmpty = TRUE) {

		$outCatalogue = new MessageCatalogue($catalogue->getLocale());

		foreach ($catalogue->all() as $domain => $messages) {
			$outMessages = array();

			foreach ($messages as $id => $translation) {

				$include = TRUE;
				foreach ($excludedPrefixes as $p) {
					if (strpos($id, $p) === 0) {	// je to prefix
						$include = FALSE;
						break;
					}
				}
				if (
					$include
					||
					($onlyEmpty && ! empty($translation))
				) {
					$outMessages[$id] = $translation;
				}

			}

			$outCatalogue->add($outMessages, $domain);
		}

		$catalogue = $outCatalogue;
	}

	/**
	 * Ze vstupního katalogu smaže prázdné nebo neprázdné překlady.
	 * @param MessageCatalogue $catalogue
	 * @param boolean $empty Mají se mazat _prázdné_ překlady?
	 */
	protected function filterTranslations(MessageCatalogue &$catalogue, $empty = FALSE) {

		$outCatalogue = new MessageCatalogue($catalogue->getLocale());

		foreach ($catalogue->all() as $domain => $messages) {
			$outMessages = array();

			foreach ($messages as $id => $translation) {
				if ($empty === empty($translation)) {
					$outMessages[$id] = $translation;
				}
			}

			$outCatalogue->add($outMessages, $domain);
		}

		$catalogue = $outCatalogue;
	}


	/**
	 * Funguje podobně jako tempnam. Vytvoří složku s unikátním názvem.
	 * @param string $dir
	 * @param string $prefix
	 * @return string|FALSE
	 */
	public static function tempnamDir($dir, $prefix) {
		$tempfile = tempnam($dir, $prefix);

		if (file_exists($tempfile))
			unlink($tempfile);

		mkdir($tempfile);

		return $tempfile;
	}

	/**
	 * Remove dir recursive
	 */
	public static function rmdir_r($dirName) {
		if (is_dir($dirName)) {
			foreach (glob($dirName . '/*') as $file) {
				if (is_dir($file))
					self::rmdir_r($file);
				else
					unlink($file);
			}
			rmdir($dirName);
		}
	}

}
