<?php declare(strict_types=1);

use doctrine\EntityViewer\Entities\Configuration;

require_once __DIR__.'/../src/Entities/Configuration.php';
require_once __DIR__.'/../src/Entities/Project.php';

/** @var Configuration $conf */
$conf = require_once __DIR__.'/../.config-entity-viewer.php';

$projectName = filter_input(INPUT_POST, 'project') ?: reset($conf->projects)->name;
$conf->projects[$projectName]->initObjectManager();

?><!DOCTYPE html>
<html lang="en">
<head>
    <title>Doctrine Entity Viewer</title>
    <link rel="stylesheet" href="doctrine-entity-viewer.css" />
    <style>
    </style>
</head>
<body>
    <section>
        <label>
            <select id="select-project" onchange="selectProject(this)">
                <option value="">-- bitte wählen --</option>
                <?php foreach ($conf->projects as $name => $project) { ?>
                <option <?= $projectName === $name ? 'selected="selected" ' : '' ?>value="<?= $name ?>"><?= $name ?></option>
                <?php } ?>
            </select>
        </label>
        <label>
            <select id="select-entity" onchange="selectEntity(this)"></select>
        </label>
        <span id="selected-entity"></span>
    </section>
    <section id="entity-dump"></section>
    <script type="text/javascript">
        function selectProject(elm) {
            if (elm && elm.value) {
                const data = new FormData();
                data.append('project', elm.value);

                fetch('project-content.php', {
                    method: 'post',
                    body: data,
                })
                    .then((response) => response.json())
                    .then((data) => {
                        let currentlySelected = document.querySelector('#select-entity').value;

                        let htmlOptions = '<option value="">-- bitte wählen --</option>';
                        for (const key in data) {
                            if (data.hasOwnProperty(key)) {
                                htmlOptions += '<option '+(currentlySelected === key ? 'selected="selected" ' : '')+'value="'+key+'" title="'+key+'">'+data[key]+'</option>'
                            }
                        }
                        document.querySelector('#select-entity').innerHTML = htmlOptions;

                        if (currentlySelected) {
                            const select = document.querySelector('#select-entity')
                            selectEntity(select.options[select.selectedIndex]);
                        }
                    });
            }
        }
        selectProject(document.querySelector('#select-project'));

        function selectEntity(elm) {
            document.querySelector('#selected-entity').innerHTML = '';
            document.querySelector('#entity-dump').innerHTML = '';

            if (elm && elm.value) {
                document.querySelector('#selected-entity').innerHTML = elm.value;

                const data = new FormData();
                data.append('project', document.querySelector('#select-project').value)
                data.append('entity', elm.value);

                fetch('entity-content.php', {
                    method: 'post',
                    body: data,
                })
                    .then((response) => response.text())
                    .then((data) => {
                        document.querySelector('#entity-dump').innerHTML = data;
                    });
            }
        }

        function retrieveMethodResult(elm) {
            const data = new FormData();
            data.append('project', document.querySelector('#select-project').value);
            data.append('entity', elm.dataset.entityName);
            data.append('method', elm.dataset.method);
            data.append('id', elm.dataset.id);

            fetch('entity-content.php', {
                method: 'post',
                body: data,
            })
                .then((response) => response.text())
                .then((data) => {
                    elm.parentNode.querySelector('.output').innerHTML = data;
                });
        }

        function retrievePropertyResult(elm) {
            const data = new FormData();
            data.append('project', document.querySelector('#select-project').value);
            data.append('entity', elm.dataset.entityName);
            data.append('property', elm.dataset.property);
            data.append('id', elm.dataset.id);

            fetch('entity-content.php', {
                method: 'post',
                body: data,
            })
                .then((response) => response.text())
                .then((data) => {
                    elm.parentNode.parentNode.nextElementSibling.querySelector('.output').innerHTML = data;
                });
        }
    </script>
</body>
</html>
