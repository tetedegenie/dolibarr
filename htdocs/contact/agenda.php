<?php
/* Copyright (C) 2004-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2013      Florian Henry		<florian.henry@open-concept.pro>
 * Copyright (C) 2013-2016 Alexandre Spangaro 	<aspangaro.dolibarr@gmail.com>
 * Copyright (C) 2014      Juanjo Menent	 	<jmenent@2byte.es>
 * Copyright (C) 2015      Jean-François Ferry	<jfefe@aternatik.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/contact/card.php
 *       \ingroup    societe
 *       \brief      Card of a contact
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/contact.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
require_once DOL_DOCUMENT_ROOT. '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

$langs->load("companies");
$langs->load("users");
$langs->load("other");
$langs->load("commercial");

$mesg=''; $error=0; $errors=array();

$action		= (GETPOST('action','alpha') ? GETPOST('action','alpha') : 'view');
$confirm	= GETPOST('confirm','alpha');
$backtopage = GETPOST('backtopage','alpha');
$id			= GETPOST('id','int');
$socid		= GETPOST('socid','int');

$object = new Contact($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels=$extrafields->fetch_name_optionals_label($object->table_element);

// Get object canvas (By default, this is not defined, so standard usage of dolibarr)
$object->getCanvas($id);
$objcanvas=null;
$canvas = (! empty($object->canvas)?$object->canvas:GETPOST("canvas"));
if (! empty($canvas))
{
    require_once DOL_DOCUMENT_ROOT.'/core/class/canvas.class.php';
    $objcanvas = new Canvas($db, $action);
    $objcanvas->getCanvas('contact', 'contactcard', $canvas);
}

if (GETPOST('actioncode','array'))
{
    $actioncode=GETPOST('actioncode','array',3);
    if (! count($actioncode)) $actioncode='0';
}
else
{
    $actioncode=GETPOST("actioncode","alpha",3)?GETPOST("actioncode","alpha",3):(GETPOST("actioncode")=='0'?'0':(empty($conf->global->AGENDA_DEFAULT_FILTER_TYPE)?'':$conf->global->AGENDA_DEFAULT_FILTER_TYPE));
}
$search_agenda_label=GETPOST('search_agenda_label');

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'contact', $id, 'socpeople&societe', '', '', 'rowid', $objcanvas); // If we create a contact with no company (shared contacts), no check on write permission

$limit = GETPOST("limit")?GETPOST("limit","int"):$conf->liste_limit;
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield='a.datep, a.id';
if (! $sortorder) $sortorder='DESC';

// Initialize technical object to manage hooks of thirdparties. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('contactcard','globalcard'));


/*
 *	Actions
 */

$parameters=array('id'=>$id, 'objcanvas'=>$objcanvas);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
    // Cancel
    if (GETPOST("cancel") && ! empty($backtopage))
    {
        header("Location: ".$backtopage);
        exit;
    }

    // Purge search criteria
    if (GETPOST("button_removefilter_x") || GETPOST("button_removefilter.x") || GETPOST("button_removefilter")) // All test are required to be compatible with all browsers
    {
        $actioncode='';
        $search_agenda_label='';
    }
}


/*
 *	View
 */


$title = (! empty($conf->global->SOCIETE_ADDRESSES_MANAGEMENT) ? $langs->trans("Contacts") : $langs->trans("ContactsAddresses"));
if (! empty($conf->global->MAIN_HTML_TITLE) && preg_match('/contactnameonly/',$conf->global->MAIN_HTML_TITLE) && $object->lastname) $title=$object->lastname;
$help_url='EN:Module_Third_Parties|FR:Module_Tiers|ES:Empresas';
llxHeader('', $title, $help_url);

$form = new Form($db);
$formcompany = new FormCompany($db);

$countrynotdefined=$langs->trans("ErrorSetACountryFirst").' ('.$langs->trans("SeeAbove").')';

if ($socid > 0)
{
    $objsoc = new Societe($db);
    $objsoc->fetch($socid);
}

if (is_object($objcanvas) && $objcanvas->displayCanvasExists($action))
{
    // -----------------------------------------
    // When used with CANVAS
    // -----------------------------------------
    if (empty($object->error) && $id)
 	{
 		$object = new Contact($db);
 		$result=$object->fetch($id);
		if ($result <= 0) dol_print_error('',$object->error);
 	}
   	$objcanvas->assign_values($action, $object->id, $object->ref);	// Set value for templates
    $objcanvas->display_canvas($action);							// Show template
}
else
{
    // -----------------------------------------
    // When used in standard mode
    // -----------------------------------------

    // Confirm deleting contact
    if ($user->rights->societe->contact->supprimer)
    {
        if ($action == 'delete')
        {
            print $form->formconfirm($_SERVER["PHP_SELF"]."?id=".$id.($backtopage?'&backtopage='.$backtopage:''),$langs->trans("DeleteContact"),$langs->trans("ConfirmDeleteContact"),"confirm_delete",'',0,1);
        }
    }

    /*
     * Onglets
     */
    $head=array();
    if ($id > 0)
    {
        // Si edition contact deja existant
        $object = new Contact($db);
        $res=$object->fetch($id, $user);
        if ($res < 0) { dol_print_error($db,$object->error); exit; }
        $res=$object->fetch_optionals($object->id,$extralabels);

        // Show tabs
        $head = contact_prepare_head($object);

        $title = (! empty($conf->global->SOCIETE_ADDRESSES_MANAGEMENT) ? $langs->trans("Contacts") : $langs->trans("ContactsAddresses"));
    }

    if (! empty($id) && $action != 'edit' && $action != 'create')
    {
        $objsoc = new Societe($db);

        /*
         * Fiche en mode visualisation
         */

        dol_htmloutput_errors($error,$errors);

        dol_fiche_head($head, 'agenda', $title, 0, 'contact');

        $linkback = '<a href="'.DOL_URL_ROOT.'/contact/list.php">'.$langs->trans("BackToList").'</a>';
        
        dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', '');
        
        print '<div class="fichecenter">';
        
        print '<div class="underbanner clearboth"></div>';
 
        $object->info($id);
        print dol_print_object_info($object, 1);
        
        print '</div>';
                
        print dol_fiche_end();

            
    	// Actions buttons
    	
        $objcon=$object;
        $object->fetch_thirdparty();
        $objthirdparty=$object->thirdparty;
    	
        $out='';
        $permok=$user->rights->agenda->myactions->create;
        if ((! empty($objthirdparty->id) || ! empty($objcon->id)) && $permok)
        {
            //$out.='<a href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create';
            if (get_class($objthirdparty) == 'Societe') $out.='&amp;socid='.$objthirdparty->id;
            $out.=(! empty($objcon->id)?'&amp;contactid='.$objcon->id:'').'&amp;backtopage=1&amp;percentage=-1';
        	//$out.=$langs->trans("AddAnAction").' ';
        	//$out.=img_picto($langs->trans("AddAnAction"),'filenew');
        	//$out.="</a>";
    	}
    
    	
    	print '<div class="tabsAction">';
    
        if (! empty($conf->agenda->enabled))
        {
        	if (! empty($user->rights->agenda->myactions->create) || ! empty($user->rights->agenda->allactions->create))
        	{
            	print '<a class="butAction" href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out.'">'.$langs->trans("AddAction").'</a>';
        	}
        	else
        	{
            	print '<a class="butActionRefused" href="#">'.$langs->trans("AddAction").'</a>';
        	}
        }
    
        print '</div>';
    
        if (! empty($conf->agenda->enabled) && (!empty($user->rights->agenda->myactions->read) || !empty($user->rights->agenda->allactions->read) ))
       	{
            $param='&id='.$id;
            if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.$contextpage;
            if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.$limit;
           	    
        
            print load_fiche_titre($langs->trans("TasksHistoryForThisContact"),'','');
    
            // List of all actions
    		$filters=array();
        	$filters['search_agenda_label']=$search_agenda_label;
        	
            show_actions_done($conf,$langs,$db,$objthirdparty,$object,0,$actioncode, '', $filters, $sortfield, $sortorder);
        }
    }
}


llxFooter();

$db->close();