<?php
namespace IdeHelper\Annotator\Traits;

use Cake\Core\App;
use Cake\Core\Plugin;

/**
 * Handles component related things
 */
trait HelperTrait {

	/**
	 * @param string $helper
	 *
	 * @return string|null
	 */
	protected function _findClassName($helper) {
		$className = App::className($helper, 'View/Helper', 'Helper');
		if ($className) {
			return $className;
		}

		$plugins = Plugin::loaded();
		foreach ($plugins as $plugin) {
			$className = App::className($plugin . '.' . $helper, 'View/Helper', 'Helper');
			if ($className) {
				return $className;
			}
		}

		return null;
	}

}
