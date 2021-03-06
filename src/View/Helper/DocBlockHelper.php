<?php
namespace IdeHelper\View\Helper;

use Bake\View\Helper\DocBlockHelper as BakeDocBlockHelper;
use Cake\Core\Configure;

class DocBlockHelper extends BakeDocBlockHelper {

	/**
	 * @var array|null
	 */
	protected static $nullableMap;

	/**
	 * Overwrite Bake plugin class method until https://github.com/cakephp/bake/pull/470 lands.
	 *
	 * @param array $propertySchema The property schema to use for generating the type map.
	 * @return array The property DocType map.
	 */
	public function buildEntityPropertyHintTypeMap(array $propertySchema) {
		$properties = [];
		foreach ($propertySchema as $property => $info) {
			if ($info['kind'] === 'column') {
				$type = $this->columnTypeToHintType($info['type']);

				$properties[$property] = $this->columnTypeNullable($info, $type);
			}
		}

		return $properties;
	}

	/**
	 * @param array $info
	 * @param string|null $type
	 *
	 * @return string
	 */
	public function columnTypeNullable(array $info, $type) {
		if (!$type || empty($info['null'])) {
			return $type;
		}

		if (static::$nullableMap === null) {
			static::$nullableMap = (array)Configure::read('IdeHelper.nullableMap');
		}

		if (isset(static::$nullableMap[$type]) && static::$nullableMap[$type] === false) {
			return $type;
		}

		$type .= '|null';

		return $type;
	}

}
