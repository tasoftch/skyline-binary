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

namespace Skyline\CLI\Project;


use Skyline\Compiler\Project\Attribute\Attribute;
use Skyline\Compiler\Project\MutableProjectInterface;
use Symfony\Component\Console\Input\InputInterface;

class InputProjectMerger
{
    public static function merge(MutableProjectInterface $project, InputInterface $input) {
        if(!$project->hasAttribute("data") && $proj = $input->getOption("project-data")) {
            $project->setAttribute(new Attribute("data", $proj));
        }

        if(!$project->hasAttribute("public") && $proj = $input->getOption("project-public")) {
            $project->setAttribute(new Attribute("public", $proj));
        }

        if(!$project->hasAttribute( Attribute::TITLE_ATTR_NAME ) && $proj = $input->getOption("title")) {
            $project->setAttribute(new Attribute(Attribute::TITLE_ATTR_NAME, $proj));
        }

        if(!$project->hasAttribute( Attribute::DESCRIPTION_ATTR_NAME ) && $proj = $input->getOption("description")) {
            $project->setAttribute(new Attribute(Attribute::DESCRIPTION_ATTR_NAME, $proj));
        }

        if(!$project->hasAttribute(Attribute::APP_ROOT_ATTR_NAME) && $proj = $input->getOption("app-dir")) {
            $project->setAttribute(new Attribute(Attribute::APP_ROOT_ATTR_NAME, $proj));
        }

        if(!$project->hasAttribute('excluded') && $proj = $input->getOption("exclude")) {
            $project->setAttribute(new Attribute("excluded", $proj));
        }
        if(!$project->hasAttribute('HTTPS') && $proj = $input->getOption("app-https")) {
            $project->setAttribute(new Attribute("HTTPS", $proj));
        }
    }
}