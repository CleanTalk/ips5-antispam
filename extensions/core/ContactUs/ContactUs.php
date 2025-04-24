<?php
/**
 * @brief		Contact Us extension
 * @author		<a href='https://www.invisioncommunity.com'>Invision Power Services, Inc.</a>
 * @copyright	(c) Invision Power Services, Inc.
 * @license		https://www.invisioncommunity.com/legal/standards/
 * @package		Invision Community
 * @subpackage	Antispam by Cleantalk
 * @since		23 Apr 2025
 */

namespace IPS\antispambycleantalk\extensions\core\ContactUs;

/* To prevent PHP errors (extending class does not exist) revealing path */

use IPS\antispambycleantalk\Application;
use IPS\Extensions\ContactUsAbstract;
use IPS\Helpers\Form;
use IPS\Settings;

use function defined;

if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * Contact Us extension
 */
class ContactUs extends ContactUsAbstract
{
	/**
	 * Process Form
	 *
	 * @param	Form            		$form	    The form
	 * @param	array                   $formFields Additional Configuration Formfields
	 * @param	array                   $options    Type Radio Form Options
	 * @param	array                   $toggles    Type Radio Form Toggles
	 * @param	array                   $disabled   Type Radio Form Disabled Options
	 * @return	void
	 */
	public function process( Form &$form, array &$formFields, array &$options, array &$toggles, array &$disabled  ) : void
	{
	}

	/**
	 * Allows extensions to do something before the form is shown... e.g. add your own custom fields, or redirect the page
	 *
	 * @param	Form		$form	    The form
     * @return	void
     */
	public function runBeforeFormOutput( Form $form ) : void
	{
        if ( empty($_POST) ) {
            return;
        }
        $values = $_POST;
        $member = \IPS\Member::loggedIn();
        if (
            \IPS\Settings::i()->ct_contact_form_check == 1 &&
            ($member && ! $member->isAdmin())
        ) {
            $request_params = [
                'post_info' => [
                    'comment_type' => 'contact'
                ],
                'sender_email' => $member->member_id ? $member->email : $values['email_address'],
                'message' => trim(strip_tags($values['contact_text'])),
                'sender_nickname' => $member->member_id ? $member->name : $values['contact_name']
            ];

            $ct_result = Application::spamCheck($request_params);

            if ( $ct_result && isset($ct_result->errno) && $ct_result->errno == 0 && $ct_result->allow == 0 ) {
                // @ToDo need to find how to output correct block message
                if ( \IPS\Request::i()->isAjax() ) {
                    $result = [
                        "validate" => 0,
                        "error" => $ct_result->comment
                    ];
                    die(json_encode($result));
                }
                die($ct_result->comment);
            }
        }
	}

	/**
	 * Handle the Form
	 *
	 * @param	array                   $values     Values from form
	 * @return	bool
	 */
	public function handleForm( array $values ) : bool
	{
        return false;
	}

}
