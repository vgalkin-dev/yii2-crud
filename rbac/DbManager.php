<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\rbac;

/**
 * Class DbManager tracks traversed path in the auth item tree.
 * @package netis\utils\rbac
 */
class DbManager extends \yii\rbac\DbManager
{
    /**
     * @var array a list of auth items between the one checked and the one assigned to the user,
     * after a successful checkAccess() call.
     */
    protected $currentPath;

    /**
     * Returns a list of auth items between the one checked and the one assigned to the user,
     * after a successful checkAccess() call.
     * @return array
     */
    public function getCurrentPath()
    {
        return $this->currentPath;
    }

    /**
     * @inheritdoc
     */
    protected function checkAccessFromCache($user, $itemName, $params, $assignments)
    {
        if (parent::checkAccessFromCache($user, $itemName, $params, $assignments)) {
            $this->currentPath[] = $itemName;
            return true;
        }
        return false;
    }

    /**
     * @inheritdoc
     */
    protected function checkAccessRecursive($user, $itemName, $params, $assignments)
    {
        if (parent::checkAccessRecursive($user, $itemName, $params, $assignments)) {
            $this->currentPath[] = $itemName;
            return true;
        }
        return false;
    }
}
