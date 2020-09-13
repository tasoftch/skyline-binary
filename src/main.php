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

use Skyline\CLI\BootstrapCommand;
use Skyline\CLI\CompileCommand;
use Skyline\CLI\FindTranslationsCommand;
use Skyline\CLI\MainCommand;
use Skyline\CLI\RouteCommand;
use Skyline\CLI\ServerCommand;

if (preg_match("/server/i", php_sapi_name())) {
    require $_SERVER["DOCUMENT_ROOT"] . DIRECTORY_SEPARATOR . "skyline.php";
    exit();
}

spl_autoload_register(function($className) {
    $file = str_replace('Skyline\CLI\\', __DIR__ . DIRECTORY_SEPARATOR . "App" . DIRECTORY_SEPARATOR, $className) . ".php";
    $file = str_replace("\\", DIRECTORY_SEPARATOR, $file);

    if(is_file($file)) {
        require $file;
        return true;
    }
    return false;
});

require "phar://skyline.phar/vendor/autoload.php";

ini_set("error_reporting", E_ALL);
ini_set("display_errors", 1);

if(php_sapi_name() == 'cli') {
    $app = new Symfony\Component\Console\Application(defined('APP_VERSION') ? APP_VERSION : "1.0");
    $app->add(new MainCommand());
    $app->add(new CompileCommand());
    //$app->add(new ServerCommand());
    $app->add(new BootstrapCommand());
    $app->add(new RouteCommand());

    // getcwd is required because this file gets executed under mapped phar.
    if(file_exists($autoloader = getcwd() . "/vendor/autoload.php")) {
    	// Autoloads the current project
		require $autoloader;

		if(is_file($commands = getcwd() . "/SkylineAppData/Compiled/commands.config.php")) {
			$commands = require $commands;
			foreach($commands as $command) {
				$app->add( new $command );
			}
		}
	}

    $app->setDefaultCommand("main");

    $app->run();
}