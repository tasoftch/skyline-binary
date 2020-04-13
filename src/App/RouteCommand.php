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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RouteCommand extends AbstractSkylineCommand
{
	protected function configure()
	{
		parent::configure();

		$this->setDescription("Routes a given request to a controller and its method")
			->setName("route")
			->addArgument("URI", InputArgument::REQUIRED, 'The request URI')
			->addOption("header", 'd', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'HTTP headers (ex: --header Content-Type:text/html');
	}

	protected function interact(InputInterface $input, OutputInterface $output)
	{

	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$URI = $input->getArgument("URI");
		$URL = parse_url($URI);

		if($output->isDebug()) {
			$this->io->title("Request");
			$data = [
				[
					'Scheme',
					$URL["scheme"] ?? 'http'
				],
				[
					'Host',
					$URL["host"] ?? 'localhost'
				],
				[
					'Port',
					$URL["port"] ?? '80'
				],
				[
					'Path',
					$URL["path"] ?? '/'
				],
			];

			if(isset($URL["query"]))
				$data[] = [
					"Query",
					$URL["query"]
				];
			if(isset($URL["fragment"]))
				$data[] = [
					"Fragment",
					$URL["fragment"]
				];

			$this->io->table([
				'Attribute',
				"Value"
			], $data);
		}



		return 0;
	}
}