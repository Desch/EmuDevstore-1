<?php
// wcf imports
require_once(WCF_DIR.'lib/action/AbstractAction.class.php');

/**
 * Extends AbstractAction by a function to validate a given security token.
 * A missing or invalid token will be result in a throw of a IllegalLinkException.
 * 
 * @author	Marcel Werk
 * @copyright	2001-2009 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf
 * @subpackage	action
 * @category 	Community Framework
 */
abstract class AbstractSecureAction extends AbstractAction {
	/**
	 * @see Action::readParameters()
	 */
	public function readParameters() {
		parent::readParameters();
		
		// check security token
		$this->checkSecurityToken();
	}
	
	/**
	 * Validates the security token.
	 */
	protected function checkSecurityToken() {
		if (!isset($_REQUEST['t']) || !WCF::getSession()->checkSecurityToken($_REQUEST['t'])) {
			throw new IllegalLinkException();
		}
	}
}
?>