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
use Symfony\Component\Translation\MessageCatalogue;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtractCommand extends Command
{

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
			->addOption('exclude-prefix-file', 'ef', InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "The file(s) with prefixes to exclude from extract.");
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

		$catalogue = new MessageCatalogue($input->getOption('catalogue-language'));
		foreach ($this->scanDirs as $dir) {
			$output->writeln(sprintf('<info>Extracting %s</info>', $dir));
			$this->extractor->extract($dir, $catalogue);
		}
		$this->excludePrefixes($catalogue, $this->excludedPrefixes, FALSE);
		
		$existingCatalogue = new MessageCatalogue($input->getOption('catalogue-language'));
		$this->loader->loadMessages($this->outputDir, $existingCatalogue);
		$this->excludePrefixes($existingCatalogue, $this->excludedPrefixes);
		$catalogue->addCatalogue($existingCatalogue);
		
		$this->writer->writeTranslations($catalogue, $this->outputFormat, array(
			'path' => $this->outputDir,
		));

		$output->writeln('');
		$output->writeln(sprintf('<info>Catalogue was written to %s</info>', $this->outputDir));

		$lang = $input->getOption('catalogue-language');
		require 'Onesky_Api.php';
		$oneSky = new \Onesky_Api();
		$oneSkyParameters = $this->serviceLocator->parameters['oneSky'];
		$oneSky->setApiKey($oneSkyParameters['apiKey'])->setSecret($oneSkyParameters['apiSecret']);
		foreach (Finder::findFiles('*'. $lang .'.po')->in($this->outputDir) as $file) {
			
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
			
			$response = $oneSky->translations('export', array(
				'project_id' => 33906,
				'locale' => $locale,
				'source_file_name' => $filename .'.'. $extension,
				'export_file_name' => $file->getFilename(),
			));
			$decodedResponse = json_decode($response, true);
			if ($decodedResponse === NULL) {
				// v pořádku
				
				file_put_contents($file->getRealPath(), $response);
			} else {
				print_r($decodedResponse);
			}
			
			$response = $oneSky->files('upload', array(
				'project_id' => 33906,
				'file' => $file->getRealPath(),
				'file_format' => 'GNU_PO',
				'locale' => $locale,
			));
			print_r(json_decode($response, true));
		}
		
		return 0;
	}
	
	protected function excludePrefixes(MessageCatalogue &$catalogue, $excludedPrefixes, $onlyEmpty = TRUE) {
		
		$outCatalogue = new MessageCatalogue($catalogue->getLocale());
		
		foreach ($catalogue->all() as $domain => $messages) {
			$outMessages = array();
			
			foreach ($messages as $id => $translation) {

				$include = TRUE;
				foreach ($excludedPrefixes as $p) {
					if (strpos($id, $p) === 0) {
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

}
