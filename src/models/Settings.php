<?php
/**
 * @link https://abm.at
 * @copyright Copyright (c) abm Feregyhazy & Simon GmbH
*/

namespace abmat\checkit\models;

use Craft;
use craft\base\Model;

/**
 * Checkit Plugin settings
 */
class Settings extends Model
{
	public $positionInEntries = '0';

	public $positionInCommmerceProducts = '0';

	public $showInformations = 1;

	/**
     * Returns a key-value array of positions.
     */
    public function getPossiblePositions(): array
    {
        $positions = [
            '0' => Craft::t('abm-checkit', 'Default')
		];

		for($i=1; $i<=5; $i++) {
			$positions[$i] = Craft::t('abm-checkit','Position {position}',[
				'position' => $i
			]);
		}

		return $positions;
    }
}