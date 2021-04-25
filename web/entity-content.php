<?php declare(strict_types=1);

use doctrine\EntityViewer\Entities\Configuration;
use doctrine\EntityViewer\Entities\Project;
use Doctrine\ORM\PersistentCollection;

function getRealClassName($object): ?string
{
    if (!is_object($object)) {
        return null;
    }

    $className = get_class($object);

    if (substr($className, 0, 22) === 'DoctrineProxies\\__CG__') {
        return substr($className, 23);
    }

    return $className;
}

function outputValue(ReflectionProperty $property, object $entity): string
{
    $property->setAccessible(true);
    $value = $property->getValue($entity);

    if ($value instanceof DateTime) {
        return $value->format('Y-m-d H:i:s');
    }

    if (is_object($value)) {
        $empty = $value instanceof PersistentCollection && !$value->count();
        $id = method_exists($value, 'getId') ? $value->getId() : null;
        $className = getRealClassName($value);

        return '<span class="property entity'.($empty?' empty':'').'"
                      onclick="retrievePropertyResult(this)"
                      data-entity-name="'.getRealClassName($entity).'"
                      data-id="'.$entity->getId().'"
                      data-property="'.$property->getName().'"
        >'.$className.(!is_null($id) ? " [$id]" : '').'</span>';
    }

    return htmlentities((string) $value);
}

function getEntity(string $id, string $entity, Project $project): object
{
    $entities = $project->getEntities($entity, ['id' => $id], 0);

    if (empty($entities)) {
        die(sprintf('<div class="error">Parent entity (%s, %s) could not be found!</div>', $entity, $id));
    }

    return reset($entities);
}

function getEntitiesFromMethod(string $id, string $method, string &$entity, Project $project)
{
    $entities = getEntity($id, $entity, $project)->$method();

    return transformResult($entities, $entity, $project);
}

function getEntitiesFromProperty(string $id, string $property, string &$entity, Project $project)
{
    $entities = $project->getEntities($entity, ['id' => $id], 0);

    if (empty($entities)) {
        die(sprintf('<div class="error">Parent entity (%s, %s) could not be found!</div>', $entity, $id));
    }

    $reflectionClass = new ReflectionClass($entity);

    while (true) {
        try {
            $reflectionProperty = new ReflectionProperty($reflectionClass->getName(), $property);
            break;
        } catch (ReflectionException $exception) {
            if (!$reflectionClass = $reflectionClass->getParentClass()) {
                die(sprintf('<div class="error">Property %s for entity %s could not be found!</div>', $property, $entity));
            }
        }
    }

    $reflectionProperty->setAccessible(true);
    $entities = $reflectionProperty->getValue(getEntity($id, $entity, $project));

    return transformResult($entities, $entity, $project);
}

function transformResult($entities, string &$entity, Project $project)
{
    if ($entities instanceof PersistentCollection) {
        $entities = $entities->toArray();
    }

    if (is_object($entities)) {
        $entity = getRealClassName($entities);

        if (array_key_exists($entity, $project->entities)) {
            return $project->getEntities($entity, ['id' => $entities->getId()], 0);
        }
    }

    if (!empty($entities)) {
        $raw = reset($entities);

        if (!is_object($raw)) {
            var_export($entities); exit;
        }

        $entity = getRealClassName($raw);
    }

    return $entities;
}

function getAllProperties(ReflectionClass $reflectionClass): array
{
    $properties = $reflectionClass->getProperties();

    while ($reflectionClass = $reflectionClass->getParentClass())
    {
        $properties = array_merge($properties, $reflectionClass->getProperties(ReflectionProperty::IS_PRIVATE));
    }

    return $properties;
}

function getMethodsHTML(string $entity, object $object, ReflectionClass $reflectionClass): string
{
    $methods = '';
    foreach ($reflectionClass->getMethods() as $method) {
        if ($method->isPublic() && empty($method->getParameters()) && substr($method->getName(), 0, 3) === 'get') {
            $methodName = $method->getName();
            $value = $object->$methodName();
            if (empty($value) || !(is_object($value) && !$value instanceof DateTime || is_array($value))) {
                continue;
            }
            if ($value instanceof PersistentCollection && !$value->count()) {
                continue;
            }

            $methods .= '
                    <span class="method"
                          onclick="retrieveMethodResult(this)"
                          data-entity-name="'.$entity.'"
                          data-id="'.$object->getId().'"
                          data-method="'.$method->getName().'"
                    >'.$method->getName().'()</span><span></span>';
        }
    }

    return $methods;
}

try {
    require_once __DIR__.'/../src/Entities/Configuration.php';
    require_once __DIR__.'/../src/Entities/Project.php';
    /** @var Configuration $conf */
    $conf = require_once __DIR__.'/../.config-entity-viewer.php';

    $projectName = filter_input(INPUT_POST, 'project') ?: reset($conf->projects)->name;
    $project = $conf->projects[$projectName];
    $project->initObjectManager();
    $project->initEntities();

    $entity = filter_input(INPUT_POST, 'entity') ?: reset($project->entities);

    $id = filter_input(INPUT_POST, 'id');
    $method = filter_input(INPUT_POST, 'method');
    $property = filter_input(INPUT_POST, 'property');

    if ($id && $method) {
        $entities = getEntitiesFromMethod($id, $method, $entity, $project);
    } else if ($id && $property) {
        $entities = getEntitiesFromProperty($id, $property, $entity, $project);
    } else {
        $rawQuery = (string) filter_input(INPUT_POST, 'query');
        $matches = [];
        preg_match_all('/[\S]+/', $rawQuery, $matches);
        $query = [];
        foreach ($matches[0] as $queryPart) {
            preg_match('/^([a-zA-Z_]+):(.*)$/', $queryPart, $matches);
            $query[$matches[1]] = eval('return '.$matches[2].';');
        }
        $entities = $project->getEntities($entity, $query, 0);
    }

    $reflectionClass = new ReflectionClass($entity);
    $propertyNames = [];
    $cols = 0;
    foreach (getAllProperties($reflectionClass) as $property) {
        $cols++;
        $propertyNames[] = $property->getName();
    } ?>
<table>
    <tr><th><?= implode('</th><th>', $propertyNames) ?></th></tr><?php

    $cnt = 0;
    foreach ($entities as $object) {
        $cnt++; ?>
        <tr class="<?= $cnt % 2 !== 0 ? 'odd' : 'even' ?>"><?php
            foreach (getAllProperties($reflectionClass) as $property) { ?>
                <td><?= outputValue($property, $object); ?></td><?php
            } ?>
        </tr>
        <tr class="<?= $cnt % 2 !== 0 ? 'odd' : 'even' ?>"><td colspan="<?= $cols ?>"><?= getMethodsHTML($entity, $object, $reflectionClass) ?><div class="output"></div></td></tr><?php
    } ?>
</table><?php

} catch (Exception $exception) {
    ?><div class="error"><?= $exception->getMessage()."<br><br>".$exception->getTraceAsString() ?></div><?php
}
