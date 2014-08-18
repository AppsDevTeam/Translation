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
class LatteExtractor extends Extractor
{

	/**
	 * {@inheritDoc}
	 */
	public function extract($directory, MessageCatalogue $catalogue)
	{
		if (self::DEBUG) {
			file_put_contents('/home/michal/tmp/log', '');
		}
		
		foreach (Finder::findFiles('*.latte', '*.phtml')->from($directory) as $file) {
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
		$buffer = NULL;
		$parser = new Parser();
		foreach ($tokens = $parser->parse(file_get_contents($file)) as $token) {
			if ($token->type !== $token::MACRO_TAG || !in_array($token->name, array('_', '/_'), TRUE)) {
				if ($buffer !== NULL) {
					$buffer .= $token->text;
				}

				continue;
			}

			if ($token->name === '/_') {
				
				if (self::DEBUG) {
					if ($file == '/media/storage/home/michal/www/genres.cz/private/app/SystemModule/controls/StockTurnoverControl.latte') {
						file_put_contents('/home/michal/tmp/log', $this->isIdValid($buffer) .': '. print_r($buffer, TRUE), FILE_APPEND);
					}
				}
				if ($this->isIdValid($buffer)) {
					$catalogue->set(($this->prefix ? $this->prefix . '.' : '') . $buffer, '');
				}
				$buffer = NULL;

			} elseif ($token->name === '_' && empty($token->value)) {
				$buffer = '';

			} else {
				$args = new MacroTokens($token->value);
				$writer = new PhpWriter($args, $token->modifiers);

				$message = $writer->write('%node.word');
				if (in_array(substr(trim($message), 0, 1), array('"', '\''), TRUE)) {
					$message = substr(trim($message), 1, -1);
				}

				if (self::DEBUG) {
					if ($file == '/media/storage/home/michal/www/genres.cz/private/app/SystemModule/controls/StockTurnoverControl.latte') {
						file_put_contents('/home/michal/tmp/log', $this->isIdValid($message) .': '. print_r($message, TRUE), FILE_APPEND);
					}
				}
				if ($this->isIdValid($message)) {
					$catalogue->set(($this->prefix ? $this->prefix . '.' : '') . $message, '');
				}
			}
		}
	}
	
}
