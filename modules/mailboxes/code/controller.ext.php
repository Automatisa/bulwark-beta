<?php

/**
 * @copyright 2014-2023 Sentora Project (http://www.sentora.org/) 
 * @copyright 2024-present Bulwark / Automatisa (GPLv3 fork of Sentora)
 * Sentora is a GPL fork of the ZPanel Project whose original header follows:
 *
 * ZPanel - A Cross-Platform Open-Source Web Hosting Control panel.
 *
 * @package ZPanel
 * @version $Id$
 * @author Bobby Allen - ballen@bobbyallen.me
 * @copyright (c) 2008-2014 ZPanel Group - http://www.zpanelcp.com/
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License v3
 *
 * This program (ZPanel) is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
class module_controller extends ctrl_module
{

    static $ok;
    static $password;
	static $badpass;
	static $badpasswordlength;
    static $alreadyexists;
    static $validemail;
    static $noaddress;
    static $badsize;
    static $editmailbox;
    static $update;
    static $delete;
    static $create;

    /**
     * The 'worker' methods.
     */
    static function ListMailboxes($uid)
    {
        global $zdbh;
        global $controller;
        $currentuser = ctrl_users::GetUserDetail($uid);
        $sql = "SELECT * FROM x_mailboxes WHERE mb_acc_fk=:userid AND mb_deleted_ts IS NULL ORDER BY mb_address_vc ASC";
        //$numrows = $zdbh->query($sql);
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':userid', $currentuser['userid']);
        $numrows->execute();

        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $sql->bindParam(':userid', $currentuser['userid']);
            $res = array();
            $sql->execute();
            while ($rowmailboxes = $sql->fetch()) {
                if ($rowmailboxes['mb_enabled_in'] == 1) {
                    $status = '<img src="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/up.gif" alt="Up"/>';
                } else {
                    $status = '<img src="modules/' . $controller->GetControllerRequest('URL', 'module') . '/assets/down.gif" alt="Down"/>';
                }
                $res[] = array('address' => $rowmailboxes['mb_address_vc'],
                    'created' => date(ctrl_options::GetSystemOption('bulwark_df'), $rowmailboxes['mb_created_ts']),
                    'size' => self::FormatMailboxSize(self::GetMailboxSizeMb($rowmailboxes)),
                    'status' => $status,
                    'id' => $rowmailboxes['mb_id_pk']);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ListCurrentMailboxes($mid)
    {
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        $sql = "SELECT * FROM x_mailboxes WHERE mb_id_pk=:mid AND mb_acc_fk=:uid AND mb_deleted_ts IS NULL ORDER BY mb_address_vc ASC";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':mid', $mid);
        $numrows->bindParam(':uid', $currentuser['userid']);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $sql->bindParam(':mid', $mid);
            $sql->bindParam(':uid', $currentuser['userid']);
            $res = array();
            $sql->execute();
            while ($rowmailboxes = $sql->fetch()) {
                if ($rowmailboxes['mb_enabled_in'] == 1) {
                    $ischeck = "CHECKED";
                } else {
                    $ischeck = NULL;
                }
                $res[] = array('address' => $rowmailboxes['mb_address_vc'],
                    'created' => date(ctrl_options::GetSystemOption('bulwark_df'), $rowmailboxes['mb_created_ts']),
                    'size' => self::GetMailboxSizeMb($rowmailboxes),
                    'ischeck' => $ischeck,
                    'id' => $rowmailboxes['mb_id_pk']);
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ListDomains($uid)
    {
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail($uid);
        $sql = "SELECT * FROM x_vhosts WHERE vh_acc_fk=:userid AND vh_enabled_in=1 AND vh_deleted_ts IS NULL ORDER BY vh_name_vc ASC";
        //$numrows = $zdbh->query($sql);
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':userid', $currentuser['userid']);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            $sql = $zdbh->prepare($sql);
            $sql->bindParam(':userid', $currentuser['userid']);
            $res = array();
            $sql->execute();
            while ($rowdomains = $sql->fetch()) {
                $res[] = array('domain' => ui_language::translate($rowdomains['vh_name_vc']));
            }
            return $res;
        } else {
            return false;
        }
    }

    static function ExecuteAddMailbox($uid, $address, $domain, $password, $size_mb = null)
    {
        global $zdbh;
        global $controller;
        $currentuser = ctrl_users::GetUserDetail($uid);
        if (fs_director::CheckForEmptyValue(self::CheckCreateForErrors($address, $domain, $password))) {
            return false;
        }
        // Tamaño del buzón (MB): por defecto max_mail_size; se descuenta de la cuota de disco del paquete.
        $size_mb = self::ResolveMailboxSize($size_mb, $currentuser, 0);
        if ($size_mb === false) {
            return false;
        }
        runtime_hook::Execute('OnBeforeCreateMailbox');
        $address = strtolower(str_replace(' ', '', $address));
        $fulladdress = strtolower(str_replace(' ', '', $address . "@" . $domain));
        self::$create = true;
        // Tamaño elegido (MB) para el backend de correo. Se define AQUÍ porque el
        // include del mailserver se ejecuta ANTES del INSERT en x_mailboxes.
        $maxMail = $size_mb;
        // Include mail server specific file here.
        $MailServerFile = __DIR__ . '/' . basename(ctrl_options::GetSystemOption('mailserver_php'));
        if (file_exists($MailServerFile))
            include($MailServerFile);

        $sql = "INSERT INTO x_mailboxes (mb_acc_fk,
											 mb_address_vc,
											 mb_quota_in,
											 mb_created_ts) VALUES (
											 :userid,
											 :fulladdress,
											 :size,
											 :time)";
        $time = time();
        $sql = $zdbh->prepare($sql);
        $sql->bindParam(':time', $time);
        $sql->bindParam(':userid', $currentuser['userid']);
        $sql->bindParam(':fulladdress', $fulladdress);
        $sql->bindParam(':size', $size_mb);
        $sql->execute();
        runtime_hook::Execute('OnAfterCreateMailbox');
        self::$ok = true;
        return true;
    }

    static function ExecuteDeleteMailbox($mid)
    {
        global $zdbh;
        global $controller;
        // HIGH-3 FIX: verify mailbox belongs to the authenticated user before deletion
        $currentuser = ctrl_users::GetUserDetail();
        $ownCheck = $zdbh->prepare("SELECT mb_id_pk FROM x_mailboxes WHERE mb_id_pk=:mid AND mb_acc_fk=:uid AND mb_deleted_ts IS NULL");
        $ownCheck->bindParam(':mid', $mid);
        $ownCheck->bindParam(':uid', $currentuser['userid']);
        $ownCheck->execute();
        if (!$ownCheck->fetch()) {
            return false;
        }
        runtime_hook::Execute('OnBeforeDeleteMailbox');
        self::$delete = true;
        $numrows = $zdbh->prepare("SELECT * FROM x_mailboxes WHERE mb_id_pk=:mid");
        $numrows->bindParam(':mid', $mid);
        $numrows->execute();
        $rowmailbox = $numrows->fetch();
        // Include mail server specific file here.
        $MailServerFile = __DIR__ . '/' . basename(ctrl_options::GetSystemOption('mailserver_php'));
        if (file_exists($MailServerFile)) {
            include($MailServerFile);
        }
        $time = time();
        $sql = "UPDATE x_mailboxes SET mb_deleted_ts=:time WHERE mb_id_pk=:mid";
        $sql = $zdbh->prepare($sql);
        $sql->bindParam(':time', $time);
        $sql->bindParam(':mid', $mid);
        $sql->execute();
        runtime_hook::Execute('OnAfterDeleteMailbox');
        self::$ok = true;
    }

    static function ExecuteUpdateMailbox($mid, $password, $enabled, $size_mb = null)
    {
        global $zdbh;
        global $controller;
        // HIGH-3 FIX: verify mailbox belongs to the authenticated user before update
        $currentuser = ctrl_users::GetUserDetail();
        $ownCheck = $zdbh->prepare("SELECT mb_id_pk FROM x_mailboxes WHERE mb_id_pk=:mid AND mb_acc_fk=:uid AND mb_deleted_ts IS NULL");
        $ownCheck->bindParam(':mid', $mid);
        $ownCheck->bindParam(':uid', $currentuser['userid']);
        $ownCheck->execute();
        if (!$ownCheck->fetch()) {
            return false;
        }
		// Contraseña OPCIONAL al editar: vacía = conservar la actual. El include del
		// mailserver (postfix.php) ya lo respeta: solo toca mailbox.password si no
		// viene vacía; x_mailboxes no almacena contraseña.
		$password = trim((string) $password);
		if ($password === '' || fs_director::CheckForEmptyValue(self::CheckPasswordForErrors($password))) {

			// Cambio opcional de tamaño del buzón (MB), descontado de la cuota de disco del paquete.
			$rowmailbox = null;
			if ($size_mb !== null && trim((string)$size_mb) !== '') {
				$numrows = $zdbh->prepare("SELECT * FROM x_mailboxes WHERE mb_id_pk=:mid");
				$numrows->bindParam(':mid', $mid);
				$numrows->execute();
				$rowmailbox = $numrows->fetch();
				$newsize = self::ResolveMailboxSize($size_mb, $currentuser, (int)$mid);
				if ($newsize === false) {
					return false;
				}
				$upd = $zdbh->prepare("UPDATE x_mailboxes SET mb_quota_in=:size WHERE mb_id_pk=:mid");
				$upd->bindParam(':size', $newsize);
				$upd->bindParam(':mid', $mid);
				$upd->execute();
			}
			runtime_hook::Execute('OnBeforeUpdateMailbox');
			$numrows = $zdbh->prepare("SELECT * FROM x_mailboxes WHERE mb_id_pk=:mid");
			$numrows->bindParam(':mid', $mid);
			$numrows->execute();
			$rowmailbox = $numrows->fetch();
			if ($enabled <> 0) {
				self::ExecuteEnableMailbox($mid);
			} else {
				self::ExecuteDisableMailbox($mid);
			}
			self::$update = true;
			// Include mail server specific file here.
			$MailServerFile = __DIR__ . '/' . basename(ctrl_options::GetSystemOption('mailserver_php'));
			if (file_exists($MailServerFile)) {
				include($MailServerFile);
			}
			runtime_hook::Execute('OnAfterUpdateMailbox');
			self::$ok = true;
			return;
		
		}
		return false;
		
    }

    static function ExecuteEnableMailbox($mid)
    {
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        runtime_hook::Execute('OnBeforeEnableMailbox');
        $sql = $zdbh->prepare("UPDATE x_mailboxes SET mb_enabled_in=1 WHERE mb_id_pk=:mid AND mb_acc_fk=:uid");
        $sql->bindParam(':mid', $mid);
        $sql->bindParam(':uid', $currentuser['userid']);
        $sql->execute();
        $retval = true;
        runtime_hook::Execute('OnAfterEnableMailbox');
        return $retval;
    }

    static function ExecuteDisableMailbox($mid)
    {
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        runtime_hook::Execute('OnBeforeDisableMailbox');
        $sql = $zdbh->prepare("UPDATE x_mailboxes SET mb_enabled_in=0 WHERE mb_id_pk=:mid AND mb_acc_fk=:uid");
        $sql->bindParam(':mid', $mid);
        $sql->bindParam(':uid', $currentuser['userid']);
        $sql->execute();
        $retval = true;
        runtime_hook::Execute('OnAfterDisableMailbox');
        return $retval;
    }

    static function CheckCreateForErrors($address, $domain, $password)
    {
        global $zdbh;
        $currentuser = ctrl_users::GetUserDetail();
        $fulladdress = strtolower(str_replace(' ', '', $address . '@' . $domain));
        if (fs_director::CheckForEmptyValue($address)) {
            self::$noaddress = true;
            return false;
        }
        if (fs_director::CheckForEmptyValue($password)) {
            self::$password = true;
            return false;
        }
        // Check for password length...
        if (strlen($password) < ctrl_options::GetSystemOption('password_minlength')) {
            self::$badpasswordlength = true;
            return false;
        }
        // Check for invalid password
        if (!self::IsValidPassword($password)) {
            self::$badpass = true;
            return false;
        }
        if (!self::IsValidEmail($fulladdress)) {
            self::$validemail = true;
            return false;
        }
        // Verify the submitted domain actually belongs to this user
        $domainCheck = $zdbh->prepare("SELECT vh_id_pk FROM x_vhosts WHERE vh_acc_fk=:uid AND vh_name_vc=:domain AND vh_enabled_in=1 AND vh_deleted_ts IS NULL");
        $domainCheck->bindParam(':uid', $currentuser['userid']);
        $domainCheck->bindParam(':domain', $domain);
        $domainCheck->execute();
        if (!$domainCheck->fetch()) {
            self::$validemail = true;
            return false;
        }
        $sql = "SELECT * FROM x_mailboxes WHERE mb_address_vc=:fulladdress AND mb_deleted_ts IS NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':fulladdress', $fulladdress);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            self::$alreadyexists = true;
            return false;
        }
        $sql = "SELECT * FROM x_forwarders WHERE fw_address_vc=:fulladdress AND fw_deleted_ts IS NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':fulladdress', $fulladdress);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            self::$alreadyexists = true;
            return false;
        }
        $sql = "SELECT * FROM x_distlists WHERE dl_address_vc=:fulladdress AND dl_deleted_ts IS NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':fulladdress', $fulladdress);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            self::$alreadyexists = true;
            return false;
        }
        $sql = "SELECT * FROM x_aliases WHERE al_address_vc=:fulladdress AND al_deleted_ts IS NULL";
        $numrows = $zdbh->prepare($sql);
        $numrows->bindParam(':fulladdress', $fulladdress);
        $numrows->execute();
        if ($numrows->fetchColumn() <> 0) {
            self::$alreadyexists = true;
            return false;
        }
        return true;
    }

	# These to help with weak passwords
    static function IsValidEmail($email)
    {
        return preg_match('/^[a-z0-9]+([_\\.-][a-z0-9]+)*@([a-z0-9]+([\.-][a-z0-9]+)*)+\\.[a-z]{2,}$/i', $email) == 1;
    }
    static function IsValidPassword($password)
    {
        return (bool) preg_match('/(?=.*\d)(?=.*[a-z])(?=.*[A-Z])/', $password);
    }
	
    static function CheckPasswordForErrors($password)
    {
        $retval = FALSE;
        if ($password == '') {
            self::$password = TRUE;
            $retval = TRUE;
        }
        if (strlen($password) < ctrl_options::GetSystemOption('password_minlength')) {
            self::$badpasswordlength = true;
            $retval = TRUE;
        }
        if (!self::IsValidPassword($password)) {
            self::$badpass = true;
            $retval = TRUE;
        }
        return $retval;
    }
	
    /**
     * Tamaño por defecto de un buzón (MB): el ajuste global max_mail_size.
     * El usuario puede elegir cualquier tamaño (límite de cordura: 10 TB);
     * el límite real es la cuota de disco del paquete.
     */
    static function GetDefaultMailboxSize()
    {
        $v = (int) ctrl_options::GetSystemOption('max_mail_size');
        return ($v > 0) ? $v : 200;
    }

    /**
     * MB totales ya reservados en buzones del usuario (sin contar $exclude_mid).
     */
    static function GetMailboxSpaceUsed($uid, $exclude_mid = 0)
    {
        global $zdbh;
        $def = self::GetDefaultMailboxSize();
        $sql = $zdbh->prepare("SELECT COALESCE(SUM(CASE WHEN mb_quota_in > 0 THEN mb_quota_in ELSE :def END), 0) AS used
                               FROM x_mailboxes
                               WHERE mb_acc_fk = :uid AND mb_deleted_ts IS NULL AND mb_id_pk <> :exmid");
        $sql->bindParam(':def', $def);
        $sql->bindParam(':uid', $uid);
        $sql->bindParam(':exmid', $exclude_mid);
        $sql->execute();
        $row = $sql->fetch();
        return (int) $row['used'];
    }

    /**
     * MB libres de la cuota de disco del paquete tras descontar los buzones.
     * Devuelve -1 si la cuota de disco es ilimitada (0).
     */
    static function GetRemainingDiskForMailboxes($currentuser, $exclude_mid = 0)
    {
        $quota_bytes = (int) $currentuser['diskquota'];
        if ($quota_bytes <= 0) {
            return -1; // ilimitado
        }
        $quota_mb = (int) round($quota_bytes / 1024000);
        return $quota_mb - self::GetMailboxSpaceUsed($currentuser['userid'], $exclude_mid);
    }

    /**
     * Resuelve y valida el tamaño (MB) solicitado para un buzón.
     * Vacío => valor por defecto (max_mail_size). Devuelve false si el valor
     * no es válido o no cabe en la cuota de disco del paquete (y fija
     * self::$badsize para mostrar el error en la interfaz).
     */
    static function ResolveMailboxSize($input, $currentuser, $exclude_mid = 0)
    {
        $input = trim((string) $input);
        if ($input === '') {
            $size = self::GetDefaultMailboxSize();
        } elseif (!ctype_digit($input)) {
            self::$badsize = true;
            return false;
        } else {
            $size = (int) $input;
        }
        if ($size < 10 || $size > 10485760) { // 10 MB .. 10 TB
            self::$badsize = true;
            return false;
        }
        $remaining = self::GetRemainingDiskForMailboxes($currentuser, $exclude_mid);
        if ($remaining >= 0 && $size > $remaining) {
            self::$badsize = true;
            return false;
        }
        return $size;
    }

    /**
     * MB efectivos de un buzón (0/ausente => max_mail_size).
     */
    static function GetMailboxSizeMb($row)
    {
        $q = (int) $row['mb_quota_in'];
        return ($q > 0) ? $q : self::GetDefaultMailboxSize();
    }

    /**
     * Formato legible del tamaño: 500 MB, 1.5 GB...
     */
    static function FormatMailboxSize($mb)
    {
        if ($mb >= 1000) {
            return rtrim(rtrim(number_format($mb / 1000, 2, '.', ''), '0'), '.') . ' GB';
        }
        return $mb . ' MB';
    }

    /**
     * End 'worker' methods.
     */

    /**
     * Webinterface sudo methods.
     */
    static function doAddMailbox()
    {
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        $size = isset($formvars['inSize']) ? $formvars['inSize'] : '';
        if (self::ExecuteAddMailbox($currentuser['userid'], $formvars['inAddress'], $formvars['inDomain'], $formvars['inPassword'], $size))
            self::$ok = true;
        return true;
    }

    static function doEditMailbox()
    {
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        foreach (self::ListMailboxes($currentuser['userid']) as $row) {
            if (isset($formvars['inDelete_' . $row['id']])) {
                header("location: ./?module=" . $controller->GetCurrentModule() . '&show=Delete&other=' . $row['id']);
                exit;
            }
            if (isset($formvars['inEdit_' . $row['id']])) {
                header('location: ./?module=' . $controller->GetCurrentModule() . '&show=Edit&other=' . $row['id']);
                exit;
            }
        }
        return true;
    }

    static function doUpdateMailbox()
    {
        global $controller;
        runtime_csfr::Protect();
        $currentuser = ctrl_users::GetUserDetail();
        $formvars = $controller->GetAllControllerRequests('FORM');
        $enabled = (isset($formvars['inEnabled'])) ? fs_director::GetCheckboxValue($formvars['inEnabled']) : 0;
        $size = isset($formvars['inSize']) ? $formvars['inSize'] : '';
        $password = isset($formvars['inPassword']) ? $formvars['inPassword'] : '';
        if (self::ExecuteUpdateMailbox($formvars['inSave'], $password, $enabled, $size))
            self::$ok = true;
        return true;
    }

    static function doConfirmDeleteMailbox()
    {
        global $controller;
        runtime_csfr::Protect();
        $formvars = $controller->GetAllControllerRequests('FORM');
        return self::ExecuteDeleteMailbox($formvars['inDelete']);
    }

    static function getMailboxList()
    {
        $currentuser = ctrl_users::GetUserDetail();
        return self::ListMailboxes($currentuser['userid']);
    }

    static function getDomainList()
    {
        $currentuser = ctrl_users::GetUserDetail();
        return self::ListDomains($currentuser['userid']);
    }

    static function getCurrentMailboxList()
    {
        global $controller;
        return self::ListCurrentMailboxes($controller->GetControllerRequest('URL', 'other'));
    }

    static function GetMailOption($name)
    {
        global $zdbh;
        $numrows = $zdbh->prepare("SELECT mbs_value_tx FROM x_mail_settings WHERE mbs_name_vc = :name");
        $numrows->bindParam(':name', $name);
        $numrows->execute();
        $result = $numrows->fetch();
        return ($result) ? $result['mbs_value_tx'] : false;
    }

    static function getisCreateMailbox()
    {
        global $controller;
        $urlvars = $controller->GetAllControllerRequests('URL');
        return !isset($urlvars['show']);
    }

    static function getisDeleteMailbox($uid = null)
    {
        global $controller;
        global $zdbh;

        $urlvars = $controller->GetAllControllerRequests('URL');

        // Verify if Current user can Delete Mail Account.
        // This shall avoid exposing mail username based on ID lookups.
        $currentuser = ctrl_users::GetUserDetail($uid);

    	$sql = "SELECT * FROM x_mailboxes WHERE mb_acc_fk=:userid AND mb_id_pk=:editedUsrID AND mb_deleted_ts IS NULL";
    	$numrows = $zdbh->prepare($sql);
    	$numrows->bindParam(':userid', $currentuser['userid']);
		$numrows->bindParam(':editedUsrID', $urlvars['other']);
    	$numrows->execute();

        if( $numrows->rowCount() == 0 ) {
            return;
        }

        // Show User Info
        return (isset($urlvars['show'])) && ($urlvars['show'] == "Delete");
    }

    static function getisEditMailbox($uid = null)
    {
		
        global $controller;
        global $zdbh;

        $urlvars     = $controller->GetAllControllerRequests('URL');

        // Verify if Current user can Edit Mail Account.
        // This shall avoid exposing mail username based on ID lookups.
        $currentuser = ctrl_users::GetUserDetail($uid);

    	$sql = "SELECT * FROM x_mailboxes WHERE mb_acc_fk=:userid AND mb_id_pk=:editedUsrID AND mb_deleted_ts IS NULL";
    	$numrows = $zdbh->prepare($sql);
    	$numrows->bindParam(':userid', $currentuser['userid']);
		$numrows->bindParam(':editedUsrID', $urlvars['other']);
    	$numrows->execute();

        if( $numrows->rowCount() == 0 ) {
            return;
        }
		
        // Show User Info
        return (isset($urlvars['show'])) && ($urlvars['show'] == "Edit");
    }


    static function getEditCurrentMailboxName()
    {
        global $controller;
        if ($controller->GetControllerRequest('URL', 'other')) {
            $current = self::ListCurrentMailboxes($controller->GetControllerRequest('URL', 'other'));
            return $current[0]['address'];
        } else {
            return '';
        }
    }

    static function getEditCurrentMailboxID()
    {
        global $controller;
        if ($controller->GetControllerRequest('URL', 'other')) {
            $current = self::ListCurrentMailboxes($controller->GetControllerRequest('URL', 'other'));
            return $current[0]['id'];
        } else {
            return "";
        }
    }

    static function getQuotaLimit()
    {
        $currentuser = ctrl_users::GetUserDetail();
        return ($currentuser['mailboxquota'] < 0) or //-1 = unlimited
                ($currentuser['mailboxquota'] > ctrl_users::GetQuotaUsages('mailboxes', $currentuser['userid']));
    }

    static function getDefaultMailSize()
    {
        return self::GetDefaultMailboxSize();
    }

    /**
     * Getters planos para la plantilla (espacio de correo vs cuota de disco del paquete).
     */
    static function isMailboxSpaceUnlimited()
    {
        $currentuser = ctrl_users::GetUserDetail();
        return ((int) $currentuser['diskquota']) <= 0;
    }

    static function getMailboxSpaceQuota()
    {
        $currentuser = ctrl_users::GetUserDetail();
        return self::FormatMailboxSize((int) round(((int) $currentuser['diskquota']) / 1024000));
    }

    static function getMailboxSpaceUsedFmt()
    {
        $currentuser = ctrl_users::GetUserDetail();
        return self::FormatMailboxSize(self::GetMailboxSpaceUsed($currentuser['userid']));
    }

    static function getMailboxSpaceFree()
    {
        $currentuser = ctrl_users::GetUserDetail();
        $remaining = self::GetRemainingDiskForMailboxes($currentuser);
        if ($remaining < 0) {
            return ui_language::translate('Unlimited');
        }
        return self::FormatMailboxSize(max($remaining, 0));
    }

    static function getEmailUsagepChart()
    {
        $currentuser = ctrl_users::GetUserDetail();
        $maximum = $currentuser['mailboxquota'];
        if ($maximum < 0) { //-1 = unlimited
            return '<img src="' . ui_tpl_assetfolderpath::Template() . 'img/misc/unlimited.png" alt="' . ui_language::translate('Unlimited') . '"/>';
        } else {
            $used = ctrl_users::GetQuotaUsages('mailboxes', $currentuser['userid']);
            $free = max($maximum - $used, 0);
            return '<img src="etc/lib/charts/svg_pie.php?score=' . $free . '::' . $used
                    . '&labels=Free:_' . $free . '::Used:_' . $used . '&imagesize=320::200"'
                    . ' alt="' . ui_language::translate('Pie chart') . '"/>';
        }
    }

    static function getMinPassLength()
    {
        $minpasswordlength = ctrl_options::GetSystemOption('password_minlength');
        $trylength = 9;
        if ($trylength < $minpasswordlength) {
            $uselength = $minpasswordlength;
        } else {
            $uselength = $trylength;
        }
        return $uselength;
    }




    static function getResult()
    {
        if (!fs_director::CheckForEmptyValue(self::$alreadyexists)) {
            return ui_sysmessage::shout(ui_language::translate('A mailbox, alias, forwarder or distribution list already exists with that name.'), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$validemail)) {
            return ui_sysmessage::shout(ui_language::translate("Your email address is not valid."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$password)) {
            return ui_sysmessage::shout(ui_language::translate("Your password cannot be blank."), "zannounceerror");
        }
		if (!fs_director::CheckForEmptyValue(self::$badpass)) {
            return ui_sysmessage::shout(ui_language::translate("Your password is not valid. Valid characters are A-Z, a-z, 0-9."), "zannounceerror");
        }
		if (!fs_director::CheckForEmptyValue(self::$badpasswordlength)) {
            return ui_sysmessage::shout(ui_language::translate("Your password did not meet the minimun length requirements. Characters needed for password length") . ": " . ctrl_options::GetSystemOption('password_minlength'), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$noaddress)) {
            return ui_sysmessage::shout(ui_language::translate("Your email address cannot be blank."), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$badsize)) {
            $msg = "Invalid mailbox size. Enter a value between 10 MB and 10 TB, within the available disk quota of your package.";
            return ui_sysmessage::shout(ui_language::translate($msg), "zannounceerror");
        }
        if (!fs_director::CheckForEmptyValue(self::$ok)) {
            return ui_sysmessage::shout(ui_language::translate("Changes to your mailboxes have been saved successfully!"), "zannounceok");
        }
        return;
    }

    /**
     * Webinterface sudo methods.
     */
}
