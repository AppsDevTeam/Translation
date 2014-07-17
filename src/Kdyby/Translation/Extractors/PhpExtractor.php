<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Translation\Extractors;

use Kdyby;
use Latte\Parser;
use Latte\MacroTokens;
use Latte\PhpWriter;
use Nette;
use Nette\Utils\Finder;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class PhpExtractor extends Nette\Object implements ExtractorInterface
{

	/**
	 * @var string
	 */
	private $prefix;



	/**
	 * {@inheritDoc}
	 */
	public function extract($directory, MessageCatalogue $catalogue)
	{
		//file_put_contents('/home/michal/tmp/log', '');
		foreach (Finder::findFiles('*.php')->from($directory) as $file) {
			$this->extractFile($file, $catalogue);
		}
	}



	/**
	 * Extracts translation messages from a file to the catalogue.
	 *
	 * @param string           $file The path to look into
	 * @param MessageCatalogue $catalogue The catalogue
	 */
	public function extractFile($file, MessageCatalogue $catalogue)
	{
		$fileContent = file_get_contents($file);
		
		// $this->translator->translate('client.form.name', NULL, array('name' => 'test'))
		// $this->translator->translate('client.form.name', NULL, ['name' => 'test'])
		// $this->translator->translate('client.form.name', NULL, $myArray)
		// $this->translator->translate('client.form.email', 0)
		preg_match_all(''
			. '/'	// [0]
			. '["\']'
			. '('
					// domain
				. '[a-zA-Z0-9_-]+'
					// parts
				. '\.'
				. '[a-zA-Z0-9_-]+'	// according to our convention, you have to specify at least 1 part between domain and word
				. '(?:\.[a-zA-Z0-9_-]+)*'
					// word
				. '\.'
				. '[a-zA-Z0-9_-]+'
			. ')'
			. '["\']'
			. '(?:'
				. '\s*,\s*'
				. '(?:'
						// pluralization
					. '([0-9,.]+|NULL)'	// [1]
					. '(?:'
						. '\s*,\s*'
							// placeholders
						. '(?|'	// [2]
								. 'array\s*\(\s*([^)]*)\s*\)'	// array()
							. '|'
								. '\[\s*([^]]*)\s*\]'	// []
							. '|'
								. '(\$[a-zA-Z0-9_]+)'	// $myArray
						. ')'
					. ')?'
				. ')'
				. '\s*[^,]'
			. ')?'
			. '/xi',
			$fileContent,
			$matches,
			PREG_SET_ORDER | PREG_OFFSET_CAPTURE
		);
		
		/*
		if ($file == '/media/storage/home/michal/www/sunkins_svetzdravi_knt_web/private/app/components/forms/clients/ClientForm.php') {
			file_put_contents('/home/michal/tmp/log', print_r($matches, TRUE), FILE_APPEND);
		}
		*/
		foreach ($matches as $match) {
			
			$id = $match[1][0];
			$translation = '';
			$line = self::offsetToLineNumber($fileContent, $match[1][1]);
			$fileName = $file->getFilename();
			
			if (isset($match[2]) && strtoupper($match[2][0]) !== 'NULL') {
				// pluralization
				
				$translation = "%count% jablko|%count% jablka|%count% jablek";
			}
			
			if (isset($match[3])) {
				// placeholders
				
				$parameters = $match[3][0];
				if (substr($parameters, 0, 1) === '$') {
					// variable
					
					$translation .= " Unknown parameters in variable $parameters: $fileName:$line";
				} else {
					// array
					
					preg_match_all('/["\']([^"\']+)["\']\s*=>\s*["\'][^"\']+["\']/', $parameters, $parameterMatches);
					$keys = array();
					foreach ($parameterMatches[1] as $p) {
						$keys[] = '%'.$p.'%';
					}
					$translation .= '; '. implode(', ', $keys);
				}
			}
			
			$domain = 'messages';
			$catalogue->set($id, $translation, $domain);
		}
	}
	
	protected static function offsetToLineNumber($fileContent, $offset) {
		return substr_count($fileContent, "\n", 0, $offset) + 1;
	}



	/**
	 * {@inheritDoc}
	 */
	public function setPrefix($prefix)
	{
		$this->prefix = $prefix;
	}

}
