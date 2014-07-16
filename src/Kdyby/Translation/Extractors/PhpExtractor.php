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
		preg_match_all('/["\']([a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-])*)["\']/', file_get_contents($file), $matches);
		
		//file_put_contents('/home/michal/tmp/log', print_r($matches, TRUE), FILE_APPEND);
		foreach ($matches[1] as $token) {
			$catalogue->set($token, $token);
		}
	}



	/**
	 * {@inheritDoc}
	 */
	public function setPrefix($prefix)
	{
		$this->prefix = $prefix;
	}

}
