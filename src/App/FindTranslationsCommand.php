<?php
/**
 * BSD 3-Clause License
 *
 * Copyright (c) 2019, TASoft Applications
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 *  Redistributions of source code must retain the above copyright notice, this
 *   list of conditions and the following disclaimer.
 *
 *  Redistributions in binary form must reproduce the above copyright notice,
 *   this list of conditions and the following disclaimer in the documentation
 *   and/or other materials provided with the distribution.
 *
 *  Neither the name of the copyright holder nor the names of its
 *   contributors may be used to endorse or promote products derived from
 *   this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */

namespace Skyline\CLI;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FindTranslationsCommand extends AbstractSkylineCommand
{
	protected function configure()
	{
		parent::configure();
		$this->setName("translate");
		$this->setDescription("Searches for translation instructions in all *.phtml templates");

		$this->addOption("live-translate", 'l', InputOption::VALUE_NONE, 'If a text is not yet translated, ask for translation');
		$this->addArgument("source-directory", InputArgument::OPTIONAL, 'Searches from this source', './');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$outputStyle = new OutputFormatterStyle('green');
		$this->io->getFormatter()->setStyle('file', $outputStyle);
		$this->io->getFormatter()->setStyle('translation', new OutputFormatterStyle("yellow", 'black'));

		$this->io->getFormatter()->setStyle('ok', new OutputFormatterStyle("green", NULL, ['bold']));

		$src = $input->getArgument("source-directory");

		$translations = [];

		if($input->getOption("live-translate")) {
			$transHandler = function($arguments) use (&$translations) {
				$firstArg = array_shift($arguments);
				if(is_string($firstArg)) {
					$trace = debug_backtrace();
					array_shift($trace);
					$trace = array_shift($trace);

					$trFile = NULL;
					foreach ($translations as $translation) {
						if (in_array($firstArg, $translation[4])) {
							$trFile[] = $translation[3];
						}
					}


				}
			};
		} else {
			$transHandler = function($arguments) use (&$translations) {
				$firstArg = array_shift($arguments);
				if(is_string($firstArg)) {
					$trace = debug_backtrace();
					array_shift($trace);
					$trace = array_shift($trace);

					$trFile = NULL;
					foreach($translations as $translation) {
						if(in_array($firstArg, $translation[4])) {
							$trFile[] = $translation[3];
						}
					}

					if($trFile)
						$trFile = sprintf("Translations %d: <ok>%s</ok>", count($trFile), implode(", ", $trFile));
					else
						$trFile = "<error>Not yet translated.</error>";

					$this->io->writeln("    Line {$trace['line']}: <translation>$firstArg</translation> => $trFile");
				}
			};
		}

		$translator = new class($transHandler) {
			private $handler;
			public function __construct($handler)
			{
				$this->handler = $handler;
			}

			public function __call($name, $arguments)
			{
				if($name == 'translate') {
					($this->handler)($arguments);
				}
			}
			public function __get($name){}
			public function __set($name, $value){}
			public static function __callStatic($name, $arguments){}
		};

		/** @var \SplFileInfo $file */
		$files = [];

		foreach(new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator($src) ) as $file) {
			if(fnmatch("*.phtml", $file->getBasename())) {
				$files[] = $file;
				continue;
			}

			if(preg_match("/^([^\.]+)\.([a-z_]+)\.loc\.php$/i", $file->getBasename(), $ms)) {
				$ms[] = $file->getPathname();
				$ms[] = array_keys( @include $file->getPathname());
				$translations[] = $ms;
			}
		}

		foreach($files as $file) {
			$this->io->writeln("File: <file>".$file->getPathname()."</file>");

			$cb = function() use ($file) {
				@include $file->getRealPath();
			};
			try {
				ob_start();
				$cb->call($translator);
			} catch (\Throwable $exception) {
				$this->io->writeln("<error> ** Error: " . $exception->getMessage() . "</error>");
			} finally {
				ob_end_clean();
			}
		}

		return 0;
	}
}