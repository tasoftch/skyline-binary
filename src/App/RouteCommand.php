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

use Skyline\Application\Exception\UnresolvedRouteException;
use Skyline\Kernel\Config\MainKernelConfig;
use Skyline\Kernel\Config\PluginConfig;
use Skyline\Render\Router\Description\MutableRegexRenderActionDescription;
use Skyline\Render\Router\Description\RegexRenderActionDescription;
use Skyline\Render\Router\Description\RenderActionDescription;
use Skyline\Router\Description\RegexActionDescription;
use Skyline\Router\Event\HTTPRequestRouteEvent;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use TASoft\EventManager\SectionEventManager;
use TASoft\Service\ServiceManager;

class RouteCommand extends AbstractSkylineCommand
{
	protected function configure()
	{
		parent::configure();

		$this->setDescription("Routes a given request to a controller and its method")
			->setName("route")
			->addArgument("URI", InputArgument::REQUIRED, 'The request URI')
			->addOption("content-type", 't', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'HTTP headers (ex: --content-type \'text/html;q=0.9\'');
	}

	protected function interact(InputInterface $input, OutputInterface $output)
	{

	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$URI = $input->getArgument("URI");
		$url = parse_url($URI);
		$url["scheme"] = $url["scheme"] ?? 'http';

		$appData = $input->getOption("app-data");



		$SERVER = [
			"HTTP_HOST" => $url["host"],
			"SERVER_ADDR" => $url["port"] ?? ($url["scheme"] == 'https' ? 443 : 80)
		];

		if($cts = $input->getOption("content-type")) {
			$SERVER["HTTP_ACCEPT"] = implode(",", $cts);
		}

		if($url["scheme"] == 'https')
			$SERVER["HTTPS"] = 'on';

		$REQUEST = Request::create($url['path'], 'GET', [], [], [], $SERVER);

		$serviceManager = NULL;


		if($output->isDebug()) {
			$this->io->title("Request");
			$data = [
				[
					'Scheme',
					$REQUEST->getScheme()
				],
				[
					'Host',
					$REQUEST->getHost()
				],
				[
					'Port',
					$REQUEST->getPort()
				],
				[
					'Path',
					$REQUEST->getRequestUri()
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

		$config = @include "$appData/Compiled/main.config.php";
		if(!$config) {
			throw new \RuntimeException("Can not read main configuration.");
		}

		$parameters = require "$appData/Compiled/parameters.config.php";
		if(!$parameters) {
			$parameters = [];
			trigger_error("Can not read parameters.", E_USER_WARNING);
		}

		global $_MAIN_CONFIGURATION;
		$_MAIN_CONFIGURATION = $config;

		ServiceManager::rejectGeneralServiceManager();
		$serviceManager = ServiceManager::generalServiceManager($config[ MainKernelConfig::CONFIG_SERVICES ]);

		foreach($parameters as $parameterName => $parameterValue)
			$serviceManager->setParameter($parameterName, $parameterValue);

		$serviceManager->addCustomArgumentHandler(function($key, $value) {
			if(is_string($value) && strpos($value, '$(') !== false)
				return SkyGetPath($value, false);
			return $value;
		}, "LOCATIONS");

		$event = new HTTPRequestRouteEvent($REQUEST);
		$event->setActionDescription(new MutableRegexRenderActionDescription());

		$serviceManager->set("request", $request = method_exists($event, 'getRequest') && ($r = $event->getRequest()) ? $r : Request::createFromGlobals());


		/** @var SectionEventManager $eventManager */
		$eventManager = $serviceManager->get( MainKernelConfig::SERVICE_EVENT_MANAGER );


		if(!$eventManager->triggerSection( PluginConfig::EVENT_SECTION_ROUTING, SKY_EVENT_ROUTE, $event )->isPropagationStopped()) {
			$e = new UnresolvedRouteException("Could not resolve route", 404);
			$e->setRouteEvent($event);
			throw $e;
		}

		$this->io->title("Result");

		$actionDescription = $event->getActionDescription();

		$data = [
			'Controller' => $actionDescription->getActionControllerClass(),
			'Method' => $actionDescription->getMethodName()
		];

		if($actionDescription instanceof RegexActionDescription) {
			$data["Captures"] = $actionDescription->getCaptures();
		}

		if($actionDescription instanceof RenderActionDescription || $actionDescription instanceof RegexRenderActionDescription)
			$data["Render"] = $actionDescription->getRenderName();

		$this->io->table([
			"Attribute",
			"Value"
		], $data);

		return 0;
	}
}