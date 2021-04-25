<?php declare(strict_types=1);

use doctrine\EntityViewer\Entities\Configuration;

require_once __DIR__.'/../src/Entities/Configuration.php';
require_once __DIR__.'/../src/Entities/Project.php';

/** @var Configuration $conf */
$conf = require_once __DIR__.'/../.config-entity-viewer.php';

$projectName = filter_input(INPUT_POST, 'project') ?: reset($conf->projects)->name;
$conf->projects[$projectName]->initObjectManager();
$conf->projects[$projectName]->initEntities();

echo json_encode($conf->projects[$projectName]->entities);
