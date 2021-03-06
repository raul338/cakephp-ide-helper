<?php
namespace IdeHelper\Annotator;

use Cake\Core\Configure;
use Cake\Utility\Inflector;
use Cake\View\View;
use Exception;
use IdeHelper\Annotation\AnnotationFactory;
use IdeHelper\View\Helper\DocBlockHelper;
use RuntimeException;

class EntityAnnotator extends AbstractAnnotator {

	/**
	 * @var array|null
	 */
	protected static $typeMap;

	/**
	 * @var array
	 */
	protected static $typeMapDefaults = [
		'mediumtext' => 'string',
		'longtext' => 'string',
		'array' => 'array',
		'json' => 'array',
	];

	/**
	 * @param string $path Path to file.
	 * @return bool
	 * @throws \RuntimeException
	 */
	public function annotate($path) {
		$name = pathinfo($path, PATHINFO_FILENAME);
		if ($name === 'Entity') {
			return false;
		}

		$content = file_get_contents($path);

		$helper = new DocBlockHelper(new View());
		/** @var \Cake\Database\Schema\TableSchema $tableSchema */
		$tableSchema = $this->getConfig('schema');
		$columns = $tableSchema->columns();

		$schema = [];
		foreach ($columns as $column) {
			$row = $tableSchema->getColumn($column);
			$row['kind'] = 'column';
			$schema[$column] = $row;
		}

		$schema = $this->hydrateSchemaFromAssoc($schema);

		$propertyHintMap = $helper->buildEntityPropertyHintTypeMap($schema);
		$propertyHintMap = $this->buildExtendedEntityPropertyHintTypeMap($schema, $helper) + $propertyHintMap;

		$propertyHintMap = array_filter($propertyHintMap);

		$annotations = $helper->propertyHints($propertyHintMap);
		$associationHintMap = $helper->buildEntityAssociationHintTypeMap($schema);

		if ($associationHintMap) {
			$annotations = array_merge($annotations, $helper->propertyHints($associationHintMap));
		}

		foreach ($annotations as $key => $annotation) {
			$annotationObject = AnnotationFactory::createFromString($annotation);
			if (!$annotationObject) {
				throw new RuntimeException('Cannot factorize annotation `' . $annotation . '`');
			}

			$annotations[$key] = $annotationObject;
		}

		return $this->_annotate($path, $content, $annotations);
	}

	/**
	 * From Bake Plugin
	 *
	 * @param array $schema
	 *
	 * @return array
	 */
	protected function hydrateSchemaFromAssoc(array $schema) {
		/** @var \Cake\ORM\AssociationCollection|\Cake\ORM\Association[] $associations */
		$associations = $this->getConfig('associations');

		foreach ($associations as $association) {
			try {
				$entityClass = '\\' . ltrim($association->getTarget()->getEntityClass(), '\\');

				if ($entityClass === '\Cake\ORM\Entity') {
					$namespace = Configure::read('App.namespace');

					list($plugin) = pluginSplit($association->getTarget()->getRegistryAlias());
					if ($plugin !== null) {
						$namespace = $plugin;
					}
					$namespace = str_replace('/', '\\', trim($namespace, '\\'));

					$entityClass = $this->_entityName($association->getTarget()->getAlias());
					$entityClass = '\\' . $namespace . '\Model\Entity\\' . $entityClass;

					if (!class_exists($entityClass)) {
						$entityClass = '\Cake\ORM\Entity';
					}
				}

				$schema[$association->getProperty()] = [
					'kind' => 'association',
					'association' => $association,
					'type' => $entityClass
				];
			} catch (Exception $exception) {
				continue;
			}
		}

		return $schema;
	}

	/**
	 * Creates the proper entity name (singular) for the specified name
	 *
	 * @param string $name Name
	 * @return string Camelized and plural model name
	 */
	protected function _entityName($name) {
		return Inflector::singularize(Inflector::camelize($name));
	}

	/**
	 * @param array $propertySchema
	 * @param \IdeHelper\View\Helper\DocBlockHelper $helper
	 *
	 * @return array
	 */
	protected function buildExtendedEntityPropertyHintTypeMap(array $propertySchema, DocBlockHelper $helper) {
		$propertyHintMap = [];

		foreach ($propertySchema as $property => $info) {
			if ($info['kind'] === 'column') {
				$type = $this->columnTypeToHintType($info['type']);
				if ($type === null) {
					continue;
				}

				$propertyHintMap[$property] = $helper->columnTypeNullable($info, $type);
			}
		}

		return $propertyHintMap;
	}

	/**
	 * Converts a column type to its DocBlock type counterpart.
	 *
	 * @see \Cake\Database\Type
	 *
	 * @param string $type The column type.
	 * @return null|string The DocBlock type, or `null` for unsupported column types.
	 */
	protected function columnTypeToHintType($type) {
		if (static::$typeMap === null) {
			static::$typeMap = (array)Configure::read('IdeHelper.typeMap') + static::$typeMapDefaults;
		}

		if (isset(static::$typeMap[$type])) {
			return static::$typeMap[$type];
		}

		return null;
	}

}
