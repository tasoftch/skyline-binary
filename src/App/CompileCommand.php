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


use Skyline\Compiler\CompilerConfiguration as CC;
use Skyline\Compiler\Project\Attribute\Attribute;
use Skyline\Compiler\Project\Project;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
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

        $this->addArgument("project-directory", InputArgument::OPTIONAL, "The project directory which is to compile", "./");

        $this->addOption("--dev", NULL, InputOption::VALUE_NONE, "If set, compiles the project for development, otherwise for online production");
        $this->addOption("--test", NULL, InputOption::VALUE_NONE, "If set, compiles the project for testing, otherwise for online production");

        $this->addOption("--bootstrap", "-b", InputOption::VALUE_OPTIONAL, "The vendor's autoload.php file of your application", "vendor/autoload.php");
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

        if($bs = $input->getOption("bootstrap")) {
            $bs = getcwd() . "/$bs";

            if(!is_file($bs)) {
                $this->io->error("Bootstrap file $bs not found.");
                die();
            }
            require $bs;
        }
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $project = $input->getOption("project");
        if(!$project) {
            $c = $this->io->choice("You did not specify any project information. Do you want to do manually or load a file?", [
                "M" => 'manually',
                'F' => 'use a config file'
            ], "manually");

            if($c == 'F') {
                do {
                    $dir = $this->io->ask("Enter a configuration file");
                    if($dir == "")
                        continue;

                    if(is_file(getcwd() . "/$dir"))
                        break;
                    $this->io->error("File $dir does not exist");
                } while(true);

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
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        print_r($input->getOptions());

        $project = $input->getOption("project");
        if(!$project) {

        } else {

        }

        $dir = realpath($input->getArgument("project-directory")) ?? getcwd();
        $list = [];
        return;
        foreach($searchPaths as $sp) {
            if(strpos($sp, ":") == false) {
                $this->io->note("Search path $sp should be formatted as: spname:path/to/directory/");
                continue;
            }
            list($n, $p) = explode(":", $sp);
            if(!is_dir(getcwd() . "/" . trim($p))) {
                $this->io->error("Directory $p does not exist");
                continue;
            }
            $list[trim($n)][] = getcwd() . "/" . trim($p);
        }
        $searchPaths = $list;
    }
}