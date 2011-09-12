<?php
/*
* 2007-2010 PrestaShop 
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author Prestashop SA <contact@prestashop.com>
*  @copyright  2007-2010 Prestashop SA
*  @version  Release: $Revision: 1.4 $
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_CAN_LOAD_FILES_'))
	exit;
	
class blockreinsurance extends Module
{
	public function __construct()
	{
		$this->name = 'blockreinsurance';
		if(version_compare(_PS_VERSION_, '1.4.0.0') >= 0)
			$this->tab = 'front_office_features';
		else
			$this->tab = 'Blocks';
		$this->version = '1.0';

		parent::__construct();

		$this->displayName = $this->l('Bloc reinsurance');
		$this->description = $this->l('Add a block to display more infos to reassure your customers');
	}
	
	public function install()
	{
		return (parent::install() AND $this->installDB() AND Configuration::updateValue('blockreinsurance_nbblocks', 5) AND $this->registerHook('footer'));
	}
	
	public function installDB()
	{
		return Db::getInstance()->Execute('
		CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'reinsurance` (
			`id_contactinfos` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`filename` VARCHAR(100) NOT NULL,
			`text` VARCHAR(300) NOT NULL,
			PRIMARY KEY (`id_contactinfos`)
		) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;');
	}
	
	public function uninstall()
	{
		//Delete configuration			
		return (Configuration::deleteByName('blockreinsurance_nbblocks') AND $this->uninstallDB() AND parent::uninstall());
	}
	
	public function uninstallDB()
	{
		return Db::getInstance()->Execute('
		DROP TABLE IF EXISTS `'._DB_PREFIX_.'reinsurance`');
	}
	
	public function addToDB()
	{
		if(isset($_POST['nbblocks']))
		{			
			for($i = 1; $i <= (int)$_POST['nbblocks']; $i++)
			{
				$filename = explode('.', $_FILES['info'.$i.'_file']['name']);
				if (isset($_FILES['info'.$i.'_file']) AND isset($_FILES['info'.$i.'_file']['tmp_name']) AND !empty($_FILES['info'.$i.'_file']['tmp_name']))
				{
					if ($error = checkImage($_FILES['info'.$i.'_file']))
						return false;
					elseif (!$tmpName = tempnam(_PS_TMP_IMG_DIR_, 'PS') OR !move_uploaded_file($_FILES['info'.$i.'_file']['tmp_name'], $tmpName))
						return false;
					elseif (!imageResize($tmpName, dirname(__FILE__).'/img/'.$filename[0].'.jpg'))
						return false;
					unlink($tmpName);
				}
				Db::getInstance()->Execute('INSERT INTO `'._DB_PREFIX_.'reinsurance` (`filename`,`text`) 
											VALUES ("'.((isset($filename[0]) AND $filename[0] != '') ? pSQL($filename[0]) : '').
											'", "'.((isset($_POST['info'.$i.'_text']) AND $_POST['info'.$i.'_text'] != '') ? pSQL($_POST['info'.$i.'_text']) : '').'")');
			}
			return true;
		} else
			return false;
	}
	
	public function removeFromDB()
	{		 
		$dir = opendir(dirname(__FILE__).'/img');
		while (false !== ($file = readdir($dir)))
		{
			$path = dirname(__FILE__).'/img/'.$file; 
			if ($file != ".." AND $file != "." AND !is_dir($file))
			{
				unlink($path);
			}
		}
		closedir($dir);

		return Db::getInstance()->Execute('DELETE FROM `'._DB_PREFIX_.'reinsurance`');
	}
		
	public function getAllFromDB()
	{
		return Db::getInstance()->ExecuteS('SELECT * FROM `'._DB_PREFIX_.'reinsurance`');
	}
		
	public function getContent()
	{
		// If we try to update the settings
		if (isset($_POST['submitModule']))
		{				
			Configuration::updateValue('blockreinsurance_nbblocks', ((isset($_POST['nbblocks']) AND $_POST['nbblocks'] != '') ? (int)$_POST['nbblocks'] : '')); 
			if($this->removeFromDB() && $this->addToDB())
				echo '<div class="conf confirm"><img src="../img/admin/ok.gif"/>'.$this->l('Configuration updated').'</div>';
			else
				echo '<div class="conf error"><img src="../img/admin/disabled.gif"/>'.$this->l('An error occurred during the save').'</div>';
		}
		
		$nb_blocks = Configuration::get('blockreinsurance_nbblocks');
		$infos = $this->getAllFromDB();

		$content = '
		<script type="text/javascript">
			$(document).ready(function(){
				var nb_blocks = 5;
				nb_blocks = $("select[name=nbblocks]").val();
				$("div.container_infos").each(function(){
					id_div = $(this).attr("id").split("container_infos");
					if(parseInt(id_div[1]) <= nb_blocks)
						$(this).show();
					else
						$(this).hide();
				});
					
				$("select[name=nbblocks]").change(function(){
					nb_blocks = $("select[name=nbblocks]").val();
					$("div.container_infos").each(function(){
						id_div = $(this).attr("id").split("container_infos");
						if(parseInt(id_div[1]) <= nb_blocks)
							$(this).show();
						else
							$(this).hide();
					});
				});	
			});	
		</script>
		<h2>'.$this->displayName.'</h2>
		<form method="post" action="'.Tools::htmlentitiesutf8($_SERVER['REQUEST_URI']).'" enctype="multipart/form-data">
			<fieldset class="width2">
				<select name="nbblocks">';	
					// Show by default 5 blocks maximum
					for($i = 1; $i <= 5; $i++)
					{
						$content .= '<option value="'.$i.'" '.(($i == $nb_blocks) ? 'selected="selected"' : '').'>'.$i.' '.$this->l('block(s)').'</option>';
					}					
		$content .= '</select>
				<div class="clear">&nbsp;</div>';					
				// Show by default 5 blocks maximum
				for($i = 1; $i <= 5; $i++)
				{
					$content .= '<div id="container_infos'.$i.'" class="container_infos"><h3>'.$this->l('Block number').' '.$i.'</h3>'.
							((!empty($infos[$i-1]) && $infos[$i-1]['filename'] != '') ? '<img src="'.Tools::getHttpHost(true)._MODULE_DIR_.$this->name.'/img/'.$infos[$i-1]['filename'].'.jpg" />' : '').
							'<div class="clear">&nbsp;</div>
							<p><label for="info'.$i.'_file">'.$this->l('Image for this block').' :</label>
							<input type="file" name="info'.$i.'_file" /></p>
							<p><label for="info'.$i.'_text">'.$this->l('Alternative text for this block').' :</label>
							<input type="text" id="info'.$i.'_text" name="info'.$i.'_text" value="'.((!empty($infos[$i-1]) && $infos[$i-1]['text'] != '') ? $infos[$i-1]['text'] : '').'" /></p></div>';
				}					
		$content .= '<div class="clear">&nbsp;</div>
				<br /><center><input type="submit" name="submitModule" value="'.$this->l('Update settings').'" class="button" /></center>
			</fieldset>
		</form>
		<div class="clear">&nbsp;</div>
		<fieldset>
			<legend>Addons</legend>
			'.$this->l('This module has been developped by PrestaShop and can only be sold through').' <a href="http://addons.prestashop.com">addons.prestashop.com</a>.<br />
			'.$this->l('Please report all bugs to').' <a href="mailto:addons@prestashop.com">addons@prestashop.com</a> '.$this->l('or using our').' <a href="http://addons.prestashop.com/contact-form.php">'.$this->l('contact form').'</a>.
		</fieldset>';
		
		return $content;
	}
	
	public function hookFooter($params)
	{	
		global $smarty;		
		
		$infos = $this->getAllFromDB();		
		
		$smarty->assign(array(
			'nbblocks' => Configuration::get('blockreinsurance_nbblocks'),
			'infos' => $infos
		));
		return $this->display(__FILE__, 'blockreinsurance.tpl');
	}
}
?>