<?php declare(strict_types=1);

namespace doctrine\EntityViewer\Entities;

class Configuration
{
    /** @var string */
    public $dataDirectory;
    /** @var Project[] */
    public $projects = [];
    /** @var int */
    public $resultsPerPage = 50;

    public function __construct(
        string $dataDirectory = __DIR__.'/../../var/data'
    )
    {
        $this->dataDirectory = $dataDirectory;
    }
}
