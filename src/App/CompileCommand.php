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

        $this->addOption("--title", '-t', InputOption::VALUE_OPTIONAL, "Specify the application's title");
        $this->addOption("--description", '-d', InputOption::VALUE_OPTIONAL, "Specify the application's description");


        $this->addOption("--dev", NULL, InputOption::VALUE_NONE, "If set, compiles the project for development, otherwise for online production");
        $this->addOption("--test", NULL, InputOption::VALUE_NONE, "If set, compiles the project for testing, otherwise for online production");

        $this->addOption("--bootstrap", "-b", InputOption::VALUE_OPTIONAL, "The vendor's autoload.php file of your application", "vendor/autoload.php");
        $this->addOption("--project", '-p', InputOption::VALUE_OPTIONAL, "Loads the project's info for compilation from specified file.");

        $this->addOption("--project-attrs", NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Attributes for the project.");

        $this->addOption("--project-root", NULL, InputOption::VALUE_OPTIONAL, "Specify the project root directory to compile in");
        $this->addOption("--project-public", NULL, InputOption::VALUE_OPTIONAL, "Specify the public directory of your app");

        $this->addOption("--search-path", NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Specify custom search paths. The count of arguments must be equal to the count of --search-type.");

        $this->addOption("--exclude", NULL, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, "Specify directories (or files) with glob pattern which should not be included.");
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

            if($c == 'M') {
                $projectRoot = $input->getOption("project-root") ?: $this->io->ask("Where should the project to be compiled in?", "./SkylineAppData");
                $public = $this->io->ask("The public directory's name", "Public");

            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = realpath($input->getArgument("project-directory")) ?? getcwd();

    }
}