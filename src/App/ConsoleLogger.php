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


use Skyline\Compiler\Context\Logger\LoggerInterface;
use Skyline\Kernel\Service\Error\AbstractErrorHandlerService;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

class ConsoleLogger implements LoggerInterface
{
    /** @var SymfonyStyle */
    private $io;
    private $respectingReporting = true;

    /**
     * ConsoleLogger constructor.
     * @param SymfonyStyle $io
     */
    public function __construct(SymfonyStyle $io, bool $respectingReporting = true)
    {
        $this->io = $io;
        $this->respectingReporting = $respectingReporting;
    }

    private function shouldLog(int $level): bool {
        $codes = 0;

        if($level == AbstractErrorHandlerService::NOTICE_ERROR_LEVEL)
            $codes = E_USER_NOTICE | E_NOTICE | E_STRICT | E_DEPRECATED;

        if($level == AbstractErrorHandlerService::WARNING_ERROR_LEVEL)
            $codes = E_USER_WARNING | E_WARNING | E_CORE_WARNING | E_COMPILE_WARNING;

        if($level == AbstractErrorHandlerService::FATAL_ERROR_LEVEL)
            $codes = E_RECOVERABLE_ERROR | E_ERROR | E_PARSE | E_USER_ERROR | E_COMPILE_ERROR | E_CORE_ERROR;

        if($this->respectingReporting) {
            return error_reporting() & $codes ? true : false;
        }
        return true;
    }

    private function logBacktrace($trace = NULL) {
        if($this->io->isDebug()) {
            if($trace == NULL) {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                array_shift($trace);
            }

            $rows = [];

            while( $stack = array_shift($trace)) {
                $rows[] = [
                    isset($stack["file"]) ? (basename($stack["file"]) . ":" . $stack["line"]) : "",
                    $stack["class"] ?? NULL,
                    $stack["function"] ?? "##"
                ];
            }

            $this->io->table([
                "File",
                "Class",
                "Function"
            ], $rows);
        }
    }


    public function logText($message, $verbosity = self::VERBOSITY_NORMAL, $context = NULL, ...$args)
    {
        if($this->io->getVerbosity() & $verbosity) {
            $this->io->text(vsprintf($message, $args));
        }
    }

    public function logNotice($message, $context = NULL, ...$args)
    {
        if($this->shouldLog(AbstractErrorHandlerService::NOTICE_ERROR_LEVEL)) {
            $this->io->note(vsprintf($message, $args));
            $this->logBacktrace();
        }
    }

    public function logWarning($message, $context = NULL, ...$args)
    {
        if($this->shouldLog(AbstractErrorHandlerService::WARNING_ERROR_LEVEL)) {
            $this->io->warning(vsprintf($message, $args));
            $this->logBacktrace();
        }
    }

    public function logError($message, $context = NULL, ...$args)
    {
        if($this->shouldLog(AbstractErrorHandlerService::FATAL_ERROR_LEVEL)) {
            $this->io->error(vsprintf($message, $args));
            $this->logBacktrace();
        }
    }

    public function logException(Throwable $exception)
    {
        $this->io->caution($exception->getMessage());
        $this->logBacktrace($exception->getTrace());
    }
}