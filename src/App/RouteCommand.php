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
use Skyline\Kernel\Bootstrap;
use Skyline\Kernel\Config\MainKernelConfig;
use Skyline\Kernel\Config\PluginConfig;
use Skyline\Kernel\Loader\RequestLoader;
use Skyline\Module\Loader\ModuleLoader;
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
			->addOption("content-type", 't', InputOption::VALUE_REQUIRED|InputOption::VALUE_IS_ARRAY, 'HTTP Content-Type Header (ex: --content-type \'text/html;q=0.9\'');
	}

	protected function interact(InputInterface $input, OutputInterface $output)
	{
		if(!$input->getArgument("URI")) {
			$uri = $this->io->ask("Please enter an URI: ");
			if($uri)
				$input->setArgument("URI", $uri);
		}
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$URI = $input->getArgument("URI");

		$appData = $input->getOption("app-data");

		if($cts = $input->getOption("content-type")) {
			$_SERVER["HTTP_ACCEPT"] = implode(",", $cts);
		}

		$components = parse_url($URI);
		if (isset($components['host'])) {
			$_SERVER['SERVER_NAME'] = $components['host'];
			$_SERVER['HTTP_HOST'] = $components['host'];
		}

		if (isset($components['scheme'])) {
			if ('https' === $components['scheme']) {
				$_SERVER['HTTPS'] = 'on';
				$_SERVER['SERVER_PORT'] = 443;
			} else {
				unset($_SERVER['HTTPS']);
				$_SERVER['SERVER_PORT'] = 80;
			}
		}

		if (isset($components['port'])) {
			$_SERVER['SERVER_PORT'] = $components['port'];
			$_SERVER['HTTP_HOST'] .= ':'.$components['port'];
		}

		if (isset($components['user'])) {
			$_SERVER['PHP_AUTH_USER'] = $components['user'];
		}

		if (isset($components['pass'])) {
			$_SERVER['PHP_AUTH_PW'] = $components['pass'];
		}

		if (!isset($components['path'])) {
			$components['path'] = '/';
		}

		$queryString = '';
		if (isset($components['query'])) {
			parse_str(html_entity_decode($components['query']), $qs);

			if (isset($query)) {
				$query = array_replace($qs, $query);
				$queryString = http_build_query($query, '', '&');
			} else {
				$query = $qs;
				$queryString = $components['query'];
			}
		} elseif (isset($query)) {
			$queryString = http_build_query($query, '', '&');
		}

		$_SERVER['REQUEST_URI'] = $components['path'].('' !== $queryString ? '?'.$queryString : '');
		$_SERVER['QUERY_STRING'] = $queryString;

		$serviceManager = NULL;

		$config = @include "$appData/Compiled/main.config.php";
		if(!$config) {
			throw new \RuntimeException("Can not read main configuration.");
		}

		ServiceManager::rejectGeneralServiceManager();
		$_SERVER["SKY_IGNORE_CLI"] = true;
		Bootstrap::bootstrap($config);

		if($output->isDebug()) {
			$REQUEST = RequestLoader::$request;

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

		$serviceManager = ServiceManager::generalServiceManager();

		$event = new HTTPRequestRouteEvent( RequestLoader::$request );
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
			[
				"Controller",
				$actionDescription->getActionControllerClass()
			],
			[
				'Method',
				$actionDescription->getMethodName()
			]
		];

		if($actionDescription instanceof RegexActionDescription) {
			$data[] = [
				"Captures",
				implode(", ", $actionDescription->getCaptures() ?: [])
			];
		}

		if($actionDescription instanceof RenderActionDescription || $actionDescription instanceof RegexRenderActionDescription)
			$data[] = [
				'Render',
				$actionDescription->getRenderName()
			];

		if(class_exists(ModuleLoader::class)) {
			$data[] = ["Module", ModuleLoader::getModuleName() ?: "-.-"];
		}

		$this->io->table([
			"Attribute",
			"Value"
		], $data);

		return 0;
	}
}