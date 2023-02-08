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


use Skyline\CLI\Project\InputProjectMerger;
use Skyline\Compiler\CompilerConfiguration;
use Skyline\Compiler\CompilerContext;
use Skyline\Compiler\CompilerFactoryInterface;
use Skyline\Compiler\CompilerInterface;
use Skyline\Compiler\Context\Code\Pattern;
use Skyline\Compiler\Context\Code\PatternExcludingSourceCodeManager;
use Skyline\Compiler\Factory\CompleteWithPackagesCompilersFactory;
use Skyline\Compiler\Project\Attribute\Attribute;
use Skyline\Compiler\Project\Attribute\AttributeCollection;
use Skyline\Compiler\Project\Attribute\CompilerContextParameterCollection;
use Skyline\Compiler\Project\Attribute\SearchPathCollection;
use Skyline\Compiler\Project\Loader\LoaderInterface;
use Skyline\Compiler\Project\MutableProjectInterface;
use Skyline\Compiler\Project\Project;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CompileCommand extends Command
{
    /** @var SymfonyStyle */
    private $io;

    protected function configure()
    {
        $this->setName("compile")
            ->setDescription("Command line tool to run the Skyline CMS Compiler");

        $this->addOption("--dev", NULL, InputOption::VALUE_NONE, "If set, compiles the project for development, otherwise for online production");
        $this->addOption("--test", NULL, InputOption::VALUE_NONE, "If set, compiles the project for testing, otherwise for online production");
        $this->addOption("--confirm", NULL, InputOption::VALUE_NONE, "If set, you need to confirm the project before compiling starts");
        $this->addOption("--zero", NULL, InputOption::VALUE_NONE, "If set, path names are stored absolute");
        $this->addOption("--with-pdo", NULL, InputOption::VALUE_NONE, "Compiler resolves required PDO and tries to setup data bases correctly.");

        $this->addOption("--autoload", "-a", InputOption::VALUE_OPTIONAL, "The vendor's autoload.php file of your application", "vendor/autoload.php");
        $this->addOption("--search-path", NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Specify search paths by using : delimiter (vendor:path/to/vendor)");

        $this->addOption("--exclude", NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Specify directories (or files) with glob pattern which should not be included.");


        $this->addOption("--project", '-p', InputOption::VALUE_OPTIONAL, "Loads the project's info for compilation from specified file.");

        $this->addOption("--project-attrs", NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Attributes for the project.");

        $this->addOption("--project-data", NULL, InputOption::VALUE_OPTIONAL, "Specify the project root directory to compile in");
        $this->addOption("--project-public", NULL, InputOption::VALUE_OPTIONAL, "Specify the public directory of your app");

        $this->addOption("--title", '-t', InputOption::VALUE_OPTIONAL, "Specify the application's title");
        $this->addOption("--description", '-d', InputOption::VALUE_OPTIONAL, "Specify the application's description");

        $this->addOption("--app-dir", NULL, InputOption::VALUE_OPTIONAL, "Specify where the running application finds the project root (from public directory)");
        $this->addOption("--app-host", NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Specify all hosts that Skyline CMS should grant access to resource files");
        $this->addOption("--app-https", NULL, InputOption::VALUE_OPTIONAL, "Compiled application will redirect incoming requests to HTTPS if needed");


    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        if($bs = $input->getOption("autoload")) {
            $bs = getcwd() . "/$bs";

            if(!is_file($bs)) {
                $this->io->error("Autoload file $bs not found.");
                die();
            }
            require $bs;
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $project = $input->getOption("project");
        if(!$project) {
            restart:
            $c = $this->io->choice("You did not specify any project information. Do you want to do manually or load a file?", [
                "M" => 'manually',
                'F' => 'use a config file',
                'D' => 'Download development project.xml',
				'L' => 'Download production project.xml',
				"A" => "Abort"
            ], "manually");

            if($c == 'F') {
                do {
                    $dir = $this->io->ask("Enter a configuration file");
                    if($dir == "")
                        break;

                    if(is_file(getcwd() . "/$dir"))
                        break;
                    $this->io->error("File $dir does not exist");
                } while(true);

                if(!$dir) {
                    goto restart;
                }
                $input->setOption("project", $dir);
            } elseif($c == 'M') {
                $title = $input->getOption("title") ?: $this->io->ask("Application's Global Title");
                $description = $input->getOption("description") ?: $this->io->ask("Application's Global Description");

                $input->setOption("title", $title);
                $input->setOption("description", $description);


                $projectRoot = $input->getOption("project-data") ?: $this->io->ask("Where should the project to be compiled in?", "./SkylineAppData");
                $input->setOption("project-data", $projectRoot);

                $public = $input->getOption("project-public") ?: $this->io->ask("The public directory's name", "Public");
                $input->setOption("project-public", $public);

                $excludes = $input->getOption("exclude");
                if(!$excludes) {
                    do {
                        $ex = $this->io->ask("File or directory name to exclude? (Let empty to continue)");
                        if(!$ex)
                            break;
                        if(!in_array($ex, $excludes))
                            $excludes[] = $ex;
                    } while(true);
                }
                $input->setOption("exclude", $excludes);

                $appRedir = $this->io->ask("Launching the real application, where is the application root? (vendor directory from public)", "../");
                $input->setOption("app-dir", $appRedir);

                $https = $this->io->choice("Application will redirect incoming requests to HTTPS if needed", ["y" => 'YES', 'n' => "NO"], "YES");
                $input->setOption("app-https", $https);

                $searchPaths = $input->getOption("search-path");
                if(!$searchPaths) {
                    do {
                        $spc = $this->io->choice("Define Search Paths", [
                            "" => 'Do not register more search paths',
                            "config" => "Directory to search for configuration files",
                            "vendor" => 'Directory to search for packages',
                            "classes" => 'Directory to search for custom classes',
                            "modules" => 'Directory to search for modules',
                            "..." => "Custom search path"
                        ], "Do not register more search paths");

                        if($spc == '')
                            break;

                        do {
                            $dir = $this->io->ask("Enter directory name");
                            if($dir == "")
                                continue 2;

                            if(is_dir(getcwd() . "/$dir"))
                                break;
                            $this->io->error("Directory $dir does not exist");
                        } while(true);

                        $searchPaths[] = $spc . ":" . getcwd() . "/$dir";
                    } while(true);
                }
                $input->setOption("search-path", $searchPaths);


                $hosts = $input->getOption("app-host");
                if(!$hosts) {
                    while(true) {
                        $host = $this->io->ask("Specify host to grant access to resources");
                        if(!$host)
                            break;

                        if(!in_array($host, $hosts))
                            $hosts[] = $host;
                    }
                }

                $input->setOption("app-host", $hosts);
            } elseif($c == 'D' || $c == 'L') {
            	$url = ($c == 'D') ? 'https://packages.skyline-cms.ch/project/dev-project.xml' : 'https://packages.skyline-cms.ch/project/live-project.xml';
				$fn = ($c == 'D') ? 'dev-project.xml' : 'live-project.xml';

				$cnt = file_get_contents($url);
				file_put_contents(getcwd() . DIRECTORY_SEPARATOR . $fn, $cnt);
				$input->setOption("project", $fn);
			}
            else {
                exit(255);
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $project = $input->getOption("project");

        $zero = (bool)$input->getOption("zero");
        if($zero && $output->getVerbosity() > $output::VERBOSITY_VERBOSE)
            $this->io->text("** Use Zero links");

        if(is_file(getcwd() . "/$project")) {
            $exts = explode(".", $project);
            $ext = array_pop($exts);

            $loaderClassName = "Skyline\\Compiler\\Project\\Loader\\" . strtoupper($ext);
            if(!class_exists($loaderClassName)) {
                $this->io->error("Skyline CMS Compiler can not load *.$ext project configuration files. Use another one or install the required project loader");
                exit(8);
            }
            if($output->getVerbosity() > $output::VERBOSITY_VERBOSE)
                $this->io->text("** Loader Class: $loaderClassName");

            /** @var LoaderInterface $loader */
            $loader = new $loaderClassName( getcwd() . "/$project" );
            $project = $loader->getProject();
        } else {
            $project = new Project();
        }

        if(!($project instanceof MutableProjectInterface)) {
            $this->io->error("Could not load project instance");
            exit(3);
        }

        InputProjectMerger::merge($project, $input);

        /** @var CompilerContextParameterCollection $ctxAttr */
        $ctxAttr = $project->getAttribute("context");
        if(!($ctxAttr instanceof CompilerContextParameterCollection))
            $ctxAttr = new CompilerContextParameterCollection("context");

        $ctxClass = $ctxAttr->getContextClass();
        /** @var CompilerContext $context */
        $context = new $ctxClass($project);
        $context->setContextParameters( $ctxAttr );

        $context->getConfiguration()[CompilerConfiguration::COMPILER_ZERO_LINKS] = $zero;
        $context->getConfiguration()[CompilerConfiguration::COMPILER_TEST] = $input->getOption("test");
        $context->getConfiguration()[CompilerConfiguration::COMPILER_DEBUG] = $input->getOption("dev");
        $context->getConfiguration()[CompilerConfiguration::COMPILER_WITH_PDO] = $input->getOption("with-pdo");

        if($excludedPathItems = $project->getAttribute("excluded")) {
            if($excludedPathItems instanceof AttributeCollection)
                $excludedPathItems = $excludedPathItems->getAttributes();
            else {
                $excludedPathItems = explode(",", $excludedPathItems->getValue());
                foreach($excludedPathItems as $idx => &$value) {
                    $value = new Attribute($idx, trim($value));
                }
            }
            $ce = new PatternExcludingSourceCodeManager($context);
            foreach($excludedPathItems as $item) {
                $ce->addPattern( new Pattern( $item->getValue() ) );
            }
            $context->setSourceCodeManager($ce);
        }


        if(!($factories = $ctxAttr->getCompilerFactories())) {
            $factories[] = CompleteWithPackagesCompilersFactory::class;
        }

        foreach($factories as $factory) {
            if(is_string($factory))
                $factory = new $factory;

            if($factory instanceof CompilerFactoryInterface || $factory instanceof CompilerInterface)
                $context->addCompiler($factory);
        }

        $context->setLogger( new ConsoleLogger($this->io) );

        if($input->isInteractive() && $input->getOption("confirm")) {
            $this->io->section(sprintf("Project %s", $project->getAttribute(Attribute::TITLE_ATTR_NAME)));

            $displayExistingFile = function($file) use ($zero) {
                if(file_exists(getcwd() . "/$file")) {
                    return sprintf("<fg=green>%s</>", $zero ? (realpath($file)) : $file);
                } else {
                    return sprintf("<fg=red>%s</>", $zero ? (realpath($file)) : $file);
                }
            };

            $rows = [];
            $rows[] = [
                'Title',
                $project->getAttribute(Attribute::TITLE_ATTR_NAME)
            ];
            $rows[] = [
                'Description',
                $project->getAttribute(Attribute::DESCRIPTION_ATTR_NAME)
            ];
            $rows[] = [
                'DEV',
                $input->getOption("dev") ? "YES" : "NO"
            ];
            $rows[] = [
                'TEST',
                $input->getOption("test") ? "YES" : "NO"
            ];
            $rows[] = [
                'ROOT',
                $displayExistingFile("./")
            ];
            $rows[] = [
                'Data',
                $displayExistingFile( $project->getAttribute("data") )
            ];
            $rows[] = [
                'Public',
                $displayExistingFile( $project->getAttribute("public") ?: "./" )
            ];
            $https = $project->getAttribute("HTTPS");
            $rows[] = [
                'HTTPS',
                $https ? ($https->getValue() === true || $https->getValue() == 'y' ? "ON" : "OFF"): 'OFF'
            ];

            if($excludedPathItems) {
                $rows[] = ["***", "***"];

                $rows[] = ["Excluded", array_shift($excludedPathItems)];
                foreach($excludedPathItems as $attr)
                    $rows[] = ["", $attr];
            }

            if($hosts = $project->getAttribute(Attribute::HOSTS_ATTR_NAME)) {
                $attrs = $hosts->getAttributes();

                $rows[] = ["***", "***"];

                $rows[] = ["HOSTS", "ACCEPTS FROM ORIGIN"];

                foreach($attrs as $name => $attr)
                    $rows[] = [$name, implode(", ", $attr->getValue())];
            }

            /** @var SearchPathCollection $sps */
            if($sps = $project->getAttribute(Attribute::SEARCH_PATHS_ATTR_NAME)) {
                $rows[] = ["***", "***"];

                $rows[] = ["SEARCH PATHS"];

                $addPath = function($path, $name = "") use (&$rows, $displayExistingFile) {
                    $rows[] = [$name, $displayExistingFile($path)];
                };

                foreach($sps->getValue() as $name => $paths) {
                    $addPath(array_shift($paths), $name);

                    foreach($paths as $path) {
                        $addPath($path);
                    }
                }
            }


            $this->io->table(
                ["Property", "Value"],
                $rows
            );

            $rows = [];

            /** @var CompilerInterface $compiler */
            foreach($context->getOrganizedCompilers() as $compiler) {
                $rows[] = [$compiler->getCompilerName(), get_class($compiler)];
            }

            $this->io->table(
                ["Compiler", "Class"],
                $rows
            );

            $mode = $this->io->choice("Continue?", [
                "y" => "Yes",
                "m" => "Modify (Not yet implemented)", // TODO: Implement modification after confirm
                "a" => "Abort"
            ], "Yes");

            if($mode == 'a') {
                $this->io->error("User aborted");
                exit(-10);
            }elseif($mode == 'm') {
                $this->io->warning("Not implemented yet!");
                exit(-10);
            }else {
                $context->compile(function(CompilerInterface $compiler) {
                    $this->io->section($compiler->getCompilerName());
                    return true;
                });

                $this->io->success("Compilation completed!");
            }
        }
        else {
            $context->compile(function(CompilerInterface $compiler) {
                $this->io->section($compiler->getCompilerName());
                return true;
            });

            $this->io->success("Compilation completed!");
        }
    }
}