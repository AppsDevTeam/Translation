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
abstract class Extractor extends Nette\Object implements ExtractorInterface
{

	/**
	 * @var string
	 */
	protected $prefix;

	const MATCH_TRANSLATION_STRING = '
		(?!\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})	# do not match IP address
		(
			# domain
			[a-zA-Z0-9_-]+
			# parts
			\.
			[a-zA-Z0-9_-]+	# according to our convention, you have to specify at least 1 part between domain and word
			(?:\.[a-zA-Z0-9_-]+)*
			# word
			\.
			[a-zA-Z0-9_-]{3,}	# last word has to be longer than one char to avoid DateTime strings (for example: j.n.Y)
		)';


	protected function isIdValid($id) {
		return preg_match('/'. self::MATCH_TRANSLATION_STRING .'/xi', $id);
	}



	/**
	 * {@inheritDoc}
	 */
	public function setPrefix($prefix)
	{
		$this->prefix = $prefix;
	}

}
