<?php

use Fern\Core\CLI\FernControllerCommand;

WP_CLI::add_command('fern:controller', FernControllerCommand::class);