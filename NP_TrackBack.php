<?php
// vim: tabstop=2:shiftwidth=2

   /* ==========================================================================================
	* Trackback 2.0 for Nucleus CMS 
	* ==========================================================================================
	* This program is free software and open source software; you can redistribute
	* it and/or modify it under the terms of the GNU General Public License as
	* published by the Free Software Foundation; either version 2 of the License,
	* or (at your option) any later version.
	*
	* This program is distributed in the hope that it will be useful, but WITHOUT
	* ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
	* FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for
	* more details.
	*
	* You should have received a copy of the GNU General Public License along
	* with this program; if not, write to the Free Software Foundation, Inc.,
	* 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA  or visit
	* http://www.gnu.org/licenses/gpl.html
	* ==========================================================================================
	*/

define('NP_TRACKBACK_LINKCHECK_STRICT', 1);
define('NP_TRACKBACK_USE_XML_PARSER', 1);
define('NP_TRACKBACK_ENCODING_DETECT_ORDER', 'ASCII,ISO-2022-JP,UTF-8,EUC-JP,SJIS');

class NP_TrackBack extends NucleusPlugin {
	var $useCurl = 1; // use curl? 2:precheck+read by curl, 1: read by curl 0: fread

	function getName()   	  { return 'TrackBack';   }
	function getAuthor() 	  { return 'rakaz + nakahara21 + hsur'; }
	function getURL()    	  { return 'http://blog.cles.jp/np_cles/category/31/subcatid/3'; }
	function getVersion()	  { return '2.0.3 jp13'; }
	function getDescription() { return '[$Revision: 1.311 $]<br />' . _TB_DESCRIPTION; }
	function hasAdminArea()   { return 1; }

	function _createItemLink($itemid, $b){
		global $CONF, $manager;
		
		$itemLink = createItemLink($itemid,'');
		if( strpos($itemLink, 'http') === 0 ){
			return $itemLink;
		}
		
		$blogurl = $b->getURL();
		if (!$blogurl) {
			$b =& $manager->getBlog($CONF['DefaultBlog']);
			$blogurl = $b->getURL();
			if (!$blogurl) {
				$blogurl = $CONF['IndexURL'];
			}
		}
		
		if(substr($blogurl, -1) == '/')  $blogurl = substr($blogurl, 0, -1);
		$usePathInfo = ($CONF['URLMode'] == 'pathinfo');
		$itemUrlOrg = $CONF['ItemURL'];
		if( ! ($usePathInfo || substr($blogurl, -4) == '.php') ) $blogurl .= '/index.php';
		$CONF['ItemURL'] = $blogurl;
		
		$itemLink = createItemLink($itemid,'');
		$CONF['ItemURL'] = $itemUrlOrg;
		
		return $itemLink;
	}

	/**************************************************************************************
	 * SKIN VARS, TEMPLATE VARS AND ACTIONS
	 */

	/*
	 * TrackBack data can be inserted using skinvars (or templatevars)
	 */
	function doSkinVar($skinType, $what = '', $tb_id = '', $amount = 'limit-1') {

		global $itemid, $manager, $CONF;

		if(preg_match('/limit/i', $tb_id)){
			$amount = $tb_id;
			$tb_id = '';
		}
		$amount = intval(str_replace('limit', '', $amount));

		if ($tb_id == '') $tb_id = intval($itemid);

		$isAcceptPing = $this->isAcceptTrackBack($tb_id);

		//if( $skinType == 'template' && (! $isAcceptPing ) ){
		//	return;
		//}
		switch ($what) {
		
			// Insert Auto-discovery RDF code
			case 'tbcode':
			case 'code':
//					if($skinType == 'item')

				$spamcheck = array (
					'type'  	=> 'tbcode',
					'id'        	=> -1,
					'title'		=> '',
					'excerpt'	=> '',
					'blogname'  	=> '',
					'url'		=> '',
					'return'	=> true,
					'live'   	=> true,
					
					/* Backwards compatibility with SpamCheck API 1*/
					'data'		=> '',
					'ipblock'   => true,
				);
				global $manager;
				//$manager->notify('SpamCheck', array ('spamcheck' => & $spamcheck));
				$spam = false;
				if (isset($spamcheck['result']) && $spamcheck['result'] == true){
					$spam = true;
				}

				if( ($skinType == 'item') && (!$spam) && $isAcceptPing  )
					$this->insertCode($tb_id);
				break;
				
			// Insert TrackBack URL
			case 'tburl':
			case 'url':
//					echo $this->getTrackBackUrl($tb_id);
				if($isAcceptPing)
					echo $this->getTrackBackUrl($tb_id);
				else
					echo 'Sorry, no trackback pings are accepted.';
				break;
			
			// Insert manual ping URL
			case 'form':
			case 'manualpingformlink':
				echo $this->getManualPingUrl($tb_id);
				break;
			
			case 'sendpinglink':
				echo $manager->addTicketToUrl($CONF['PluginURL'] . 'trackback/index.php?action=ping&amp;id=' . intval($tb_id));
				break;

			// Insert TrackBack count
			case 'count':
				$count = $this->getTrackBackCount($tb_id);
				switch ($count) {
					case 0: 	echo TEMPLATE::fill($this->getOption('tplTbNone'), array('number' => $count)); break;
					case 1: 	echo TEMPLATE::fill($this->getOption('tplTbOne'),  array('number' => $count)); break;
					default: 	echo TEMPLATE::fill($this->getOption('tplTbMore'), array('number' => $count)); break;
				}
				break;

			// Shows the TrackBack list
			case 'list':
			case '':
//					$this->showList($tb_id);
				$this->showList($tb_id, $amount);
				break;
			// show requred URL
			case 'required':
				echo  $this->getRequiredURL($tb_id);
				break;
				
			// shows the Local list
			case 'locallist':
				$this->showLocalList($tb_id);
				break;
				
			default:
				return;
		}
	}

	/*
	 * When used in templates, the tb_id will be determined by the itemid there
	 */
	function doTemplateVar(&$item, $what = '') {
		$this->doSkinVar('template', $what, $item->itemid);
	}
	
	function doTemplateCommentsVar(&$item, &$comment, $what = ''){
		$this->doSkinVar('templatecomments', $what, $item->itemid);
	}
	
	/*
	* A trackback ping is to be received on the URL
	* http://yourdomain.com/item/1234.trackback
	* Extra variables to be passed along are url, title, excerpt, blog_name
	*/
	function event_InitSkinParse(&$data) {
		global $CONF, $itemid;
		$format = requestVar('format');
		
		if ($CONF['URLMode'] == 'pathinfo') {
			if (preg_match('/(\/|\.)(trackback)(\/|$)/', serverVar('PATH_INFO'), $matches)) {
				$format = $matches[2];
			}
		}
		
		if ($format == 'trackback' && $data['type'] == 'item')
		{
			$errorMsg = $this->handlePing(intval($itemid));
			
			if ($errorMsg != '')
			$this->xmlResponse($errorMsg);
			else
			$this->xmlResponse();
			
			exit;
		}
	}

	/*
	 * A trackback ping is to be received on the URL
	 * http://yourdomain.com/action.php?action=plugin&name=TrackBack&tb_id=1234
	 * Extra variables to be passed along are url, title, excerpt, blog_name
	 */
	function doAction($type)
	{
		global $CONF,$manager;
		$aActionsNotToCheck = array(
			'',
			'ping',
			'form',
			'redirect',
			'left',
		);
		if (!in_array($type, $aActionsNotToCheck)) {
			if (!$manager->checkTicket()) return _ERROR_BADTICKET;
		}
		
		switch ($type) {

			// When no action type is given, assume it's a ping
			case '':
				$errorMsg = $this->handlePing();
				$this->xmlResponse($errorMsg);
				break;
				
			// Manual ping
			case 'ping':
				$errorMsg = $this->handlePing();
				if ($errorMsg != '')
					$this->showManualPingError(intRequestVar('tb_id'), $errorMsg);
				else
					$this->showManualPingSuccess(intRequestVar('tb_id'));
				break;

			// Show manual ping form
			case 'form':
//					$this->showManualPingForm(intRequestVar('tb_id'));
				$tb_id = intRequestVar('tb_id');
				$isAcceptPing = $this->isAcceptTrackBack($tb_id);
				
				if( $isAcceptPing )
					$this->showManualPingForm($tb_id);
				else
					echo 'Sorry, no trackback pings are accepted.';
				break;

			// Detect trackback
			case 'detect':
				list($url, $title) =
					$this->getURIfromLink(html_entity_decode(requestVar('tb_link')));

				$url = addslashes($url);
				$url = $this->_utf8_to_javascript($url);

				$title = addslashes($title);
				$title = $this->_utf8_to_javascript($title);
			
				echo "tbDone('" . requestVar('tb_link') . "', '" . $url . "', '" . $title . "');";

				break;
			// redirect
			case 'redirect':
				return $this->redirect(intRequestVar('tb_id'), requestVar('urlHash'));
				break;
			case 'left':
				echo $this->showLeftList(intRequestVar('tb_id'), intRequestVar('amount'));
				break;
			
			// delete a trackback(local)
			case 'deletelc':
				$err = $this->deleteLocal(intRequestVar('tb_id'), intRequestVar('from_id'));
				if( $err )
					return $err;
				header('Location: ' . serverVar('HTTP_REFERER'));
				break;
		}

		exit;
	}
	
	function doIf($key = '', $value = '')
	{
		global $itemid;
		//echo "key: $key, value: $value";
		
		switch( strtolower($key) ){
			case '':
			case 'accept':
				if( $value == '' ) $value = 'yes';
				$value = ( $value == 'no' || (! $value) ) ? false : true;
			
				$ret = false;
				if( $itemid )
					$ret = $this->isAcceptTrackBack($itemid);
				else
					$ret = $this->isAcceptTrackBack();
				return ( $value == false ) ? (! $ret) : $ret;
				
			case 'required':
				if( $value == '' ) $value = 'yes';
				$value = ( $value == 'no' || (! $value) ) ? false : true;
				
				$ret = false;
				if( $itemid )
					$ret = $this->isEnableLinkCheck($itemid);
				
				return ( $value == false ) ? (! $ret) : $ret;
				
			default:
				return false;
		}
	}

	/**************************************************************************************
	 * OUTPUT
	 */

	/*
	 * Show a list of left trackbacks for this ID
	 */
	function showLeftList($tb_id, $offset = 0, $amount = 99999999) {
		global $manager, $blog, $CONF;

		$out = array();
		$query = '
			SELECT
				url,
				md5(url) as urlHash,
				blog_name,
				excerpt,
				title,
				UNIX_TIMESTAMP(timestamp) AS timestamp
			FROM
				'.sql_table('plugin_tb').'
			WHERE
				tb_id = '.intval($tb_id).' AND
				block = 0
			ORDER BY
				timestamp DESC
		';
		if($offset)
			$query .= ' LIMIT '.intval($offset).', ' .intval($amount);
		$res = sql_query($query);
		while ($row = sql_fetch_array($res))
		{

			$row['blog_name'] 	= htmlspecialchars($row['blog_name'], ENT_QUOTES);
			$row['title']  		= htmlspecialchars($row['title'], ENT_QUOTES);
			$row['excerpt']  	= htmlspecialchars($row['excerpt'], ENT_QUOTES);
			if (_CHARSET != 'UTF-8') {
				$row['blog_name'] 	= $this->_restore_to_utf8($row['blog_name']);
				$row['title'] 		= $this->_restore_to_utf8($row['title']);
				$row['excerpt'] 	= $this->_restore_to_utf8($row['excerpt']);
				$row['blog_name'] 	= $this->_utf8_to_entities($row['blog_name']);
				$row['title'] 		= $this->_utf8_to_entities($row['title']);
				$row['excerpt'] 	= $this->_utf8_to_entities($row['excerpt']);
			}
			$iVars = array(
				'action' 	=> $this->getTrackBackUrl($tb_id),
				'form' 	 	=> $this->getManualPingUrl($tb_id),
				'name'  	=> $row['blog_name'],
				'title' 	=> $row['title'],
				'excerpt'	=> $this->_cut_string($row['excerpt'], 400),
				'url'		=> htmlspecialchars($row['url'], ENT_QUOTES),
				'date'	   	=> htmlspecialchars(strftime($this->getOption('dateFormat'), $row['timestamp']), ENT_QUOTES)
			);

			if( $this->getOption('HideUrl') == 'yes' )
				$iVars['url'] = $CONF['ActionURL'] . '?action=plugin&amp;name=TrackBack&amp;type=redirect&amp;tb_id=' . $tb_id . '&amp;urlHash=' . $row['urlHash'];
			else
				$iVars['url'] = $row['url'];

			$out[] = TEMPLATE::fill($this->getOption('tplItem'), $iVars);
		}
		sql_free_result($res);
		
		return @join("\n",$out);
	}

	/*
	 * Show a list of all trackbacks for this ID
	 */
	function showList($tb_id, $amount = 0) {
		$tb_id = intval($tb_id);
		global $manager, $blog, $CONF, $member;
		$enableHideurl = true;
		// for TB LinkLookup
		if(
			   strpos(serverVar('HTTP_USER_AGENT'),'Hatena Diary Track') === false
			|| strpos(serverVar('HTTP_USER_AGENT'),'NP_TrackBack') === false
			|| strpos(serverVar('HTTP_USER_AGENT'),'TBPingLinkLookup') === false
			|| strpos(serverVar('HTTP_USER_AGENT'),'MT::Plugin::BanNoReferTb') === false
			|| strpos(serverVar('HTTP_USER_AGENT'),'livedoorBlog') === false
		){
			$enableHideurl = false;
			$amount = '-1';
		}

		$query = '
			SELECT
				url,
				md5(url) as urlHash,
				blog_name,
				excerpt,
				title,
				UNIX_TIMESTAMP(timestamp) AS timestamp
			FROM
				'.sql_table('plugin_tb').'
			WHERE
				tb_id = '.intval($tb_id) .' AND
				block = 0
			ORDER BY
				timestamp DESC
		';
		if( $amount == '-1' )
			$query .= ' LIMIT 9999999';
		elseif( $amount )
			$query .= ' LIMIT '.intval($amount);
		
		if( $amount != 0)
			$res = sql_query($query);

		$gVars = array(
			'action' => $this->getTrackBackUrl(intval($tb_id)),
			'form' 	 => $this->getManualPingUrl(intval($tb_id)),
			'required' => $this->getRequiredURL(intval($tb_id)),
		);
		
		if ( $member->isLoggedIn() ){
			$adminurl = htmlspecialchars($manager->addTicketToUrl($CONF['PluginURL'] . 'trackback/index.php?action=list&id=' . intval($tb_id)), ENT_QUOTES);
			$pingformurl = htmlspecialchars($manager->addTicketToUrl($CONF['PluginURL'] . 'trackback/index.php?action=ping&id=' . intval($tb_id)), ENT_QUOTES);
			$gVars['admin'] = '<a href="' . $adminurl . '" target="_blank">[admin]</a>';
			$gVars['pingform'] = '<a href="' . $pingformurl . '" target="_blank">[pingform]</a>';
		}

		echo TEMPLATE::fill($this->getOption('tplHeader'), $gVars);


		while ($amount != 0 && $row = sql_fetch_array($res))
		{

			$row['blog_name'] 	= htmlspecialchars($row['blog_name'], ENT_QUOTES);
			$row['title']  		= htmlspecialchars($row['title'], ENT_QUOTES);
			$row['excerpt']  	= htmlspecialchars($row['excerpt'], ENT_QUOTES);

			if (_CHARSET != 'UTF-8') {
				$row['blog_name'] 	= $this->_restore_to_utf8($row['blog_name']);
				$row['title'] 		= $this->_restore_to_utf8($row['title']);
				$row['excerpt'] 	= $this->_restore_to_utf8($row['excerpt']);

				$row['blog_name'] 	= mb_convert_encoding($row['blog_name'], _CHARSET, 'UTF-8');
				$row['title'] 		= mb_convert_encoding($row['title'], _CHARSET, 'UTF-8');
				$row['excerpt'] 	= mb_convert_encoding($row['excerpt'], _CHARSET, 'UTF-8');
			}				

			$iVars = array(
				'action' 	=> $this->getTrackBackUrl($tb_id),
				'form' 	 	=> $this->getManualPingUrl($tb_id),
				'name'  	=> htmlspecialchars($row['blog_name'], ENT_QUOTES),
				'title' 	=> htmlspecialchars($row['title'], ENT_QUOTES),
				'excerpt'	=> htmlspecialchars($this->_cut_string($row['excerpt'], 400), ENT_QUOTES),
				'url'		=> htmlspecialchars($row['url'], ENT_QUOTES),
				'date'	   	=> htmlspecialchars(strftime($this->getOption('dateFormat'), $row['timestamp']), ENT_QUOTES)
			);

			if( $enableHideurl && $this->getOption('HideUrl') == 'yes' )
				$iVars['url'] = $CONF['ActionURL'] . '?action=plugin&amp;name=TrackBack&amp;type=redirect&amp;tb_id=' . intval($tb_id) . '&amp;urlHash=' . $row['urlHash'];
			else
				$iVars['url'] = $row['url'];

			echo TEMPLATE::fill($this->getOption('tplItem'), $iVars);
			
		}

		$q = '
			SELECT
				count(*)
			FROM
				'.sql_table('plugin_tb').'
			WHERE
				tb_id = '.intval($tb_id) .' AND
				block = 0
			ORDER BY
				timestamp DESC
		';
		$result = sql_query($q);
		$total = sql_result($result,0,0);

		if($amount != -1 && $total > $amount){
			$leftcount = $total - $amount;

			echo '<script type="text/javascript" src="' . $this->getAdminURL() . 'detectlist.php?tb_id='.intval($tb_id).'&amp;amount='.intval($amount).'"></script>';

?>

<a name="restoftrackback" id="restoftrackback"></a>
<div id="tbshownavi"><a href="#restoftrackback" onclick="resttbStart(); return false;" id="tbshow">Show left <?php echo $leftcount;?> Trackbacks</a></div>
<div id="tbhidenavi" style="display: none;"><a href="#restoftrackback" onclick="hideresttb(); return false;">Hide <?php echo $leftcount;?> Trackbacks</a></div>
<div id="resttb"></div>

<?php
		}

		if (sql_num_rows($res) == 0)
		{
			echo TEMPLATE::fill($this->getOption('tplEmpty'), $gVars);
		}
		sql_free_result($res);
		
		echo TEMPLATE::fill($this->getOption('tplFooter'), $gVars);

	}
		
	/*
	 * Returns the TrackBack count for a TrackBack item
	 */
	function getTrackBackCount($tb_id) {
		return quickQuery('SELECT COUNT(*) as result FROM ' . sql_table('plugin_tb') . ' WHERE tb_id='.intval($tb_id).' AND block = 0');
	}
	
	/**
	  * Returns the manual ping URL
	  */
	function getManualPingUrl($itemid) {
		global $CONF;
		return $CONF['ActionURL'] . '?action=plugin&amp;name=TrackBack&amp;type=form&amp;tb_id='.$itemid;
	}

	/**
	  * Show the manual ping form
	  */
	function showManualPingError($itemid, $status = '') {
		global $CONF;

		$form = true; $error = true; $success = false;
		sendContentType('text/html', 'admin-trackback', _CHARSET);
		require_once($this->getDirectory() . '/template.php');
		$mTemplate = new Trackback_Template(null, $this->getDirectory());
		$mTemplate->set ('CONF', $CONF);
		$mTemplate->set ('itemid', $itemid);
		$mTemplate->set ('form', $form);
		$mTemplate->set ('error', $error);
		$mTemplate->set ('success', $success);
		$mTemplate->set ('status', $status);
		$mTemplate->template('templates/form.html');
		echo $mTemplate->fetch();
	}
	
	function showManualPingSuccess($itemid, $status = '') {
		global $CONF;

		$form = false; $error = false; $success = true;
		sendContentType('text/html', 'admin-trackback', _CHARSET);
		//include ($this->getDirectory() . '/templates/form.html');
		require_once($this->getDirectory() . '/template.php');
		$mTemplate = new Trackback_Template(null, $this->getDirectory());
		$mTemplate->set ('CONF', $CONF);
		$mTemplate->set ('itemid', $itemid);
		$mTemplate->set ('form', $form);
		$mTemplate->set ('error', $error);
		$mTemplate->set ('success', $success);
		$mTemplate->set ('status', $status);
		$mTemplate->template('templates/form.html');
		echo $mTemplate->fetch();
	}
	
	function showManualPingForm($itemid, $text = '') {
		global $CONF;

		$form = true; $error = false; $success = false;

		// Check if we are allowed to accept pings
		if ( !$this->isAcceptTrackBack($itemid) ) {
			$text = 'Sorry, no trackback pings are accepted';
			$form = false; $error = true;
		}
		
		sendContentType('text/html', 'admin-trackback', _CHARSET);
		//include ($this->getDirectory() . '/templates/form.html');
		require_once($this->getDirectory() . '/template.php');
		$mTemplate = new Trackback_Template(null, $this->getDirectory());
		$mTemplate->set ('CONF', $CONF);
		$mTemplate->set ('itemid', $itemid);
		$mTemplate->set ('form', $form);
		$mTemplate->set ('error', $error);
		$mTemplate->set ('success', $success);
		$mTemplate->set ('status', $status);
		$mTemplate->template('templates/form.html');
		echo $mTemplate->fetch();
	}

	/**
	  * Returns the trackback URL
	  */
	function getTrackBackUrl($itemid) {
		global $CONF, $manager;
		return $CONF['ActionURL'] . '?action=plugin&amp;name=TrackBack&amp;tb_id='.$itemid;
	}		

	/*
	 * Insert RDF code for item
	 */
	function insertCode($itemid) {
		$itemid = intval($itemid);
		global $manager, $CONF;

		$item = & $manager->getItem($itemid, 0, 0);
		$blog = & $manager->getBlog(getBlogIDFromItemID($item['itemid']));
			
		$uri 	= $this->_createItemLink($item['itemid'],$blog);
				
		$title  = strip_tags($item['title']);
		$desc  	= strip_tags($item['body']);
		$desc   = $this->_cut_string($desc, 200);
		$desc   = htmlspecialchars($desc, ENT_QUOTES);
		
		?>
		<!--
		<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
				 xmlns:dc="http://purl.org/dc/elements/1.1/"
				 xmlns:trackback="http://madskills.com/public/xml/rss/module/trackback/">
		<rdf:Description
				 rdf:about="<?php echo $uri; ?>"
				 dc:identifier="<?php echo $uri; ?>"
				 dc:title="<?php echo $title; ?>"
				 dc:description="<?php echo $desc; ?>"
				 trackback:ping="<?php echo $this->getTrackBackUrl($itemid)?>"
				 dc:date="<?php echo strftime('%Y-%m-%dT%H:%M:%S')?>" />
		</rdf:RDF>
		-->
		<?php
	}

	/**
	 * Retrieving TrackBack Pings (when __mode=rss)
	 */
	function rssResponse($tb_id) {
		$itemid = intval($itemid);
		global $manager, $CONF;
		$item =& $manager->getItem($tb_id, 0, 0);

		if($item)
		{
			$blog =& $manager->getBlog(getBlogIDFromItemID($item['itemid']));
			
			$blog_name  = $blog->getName();
			$title      = $item['title'];
			$excerpt    = $item['body'];

			$title      = $this->_restore_to_utf8($title);
			$excerpt    = $this->_restore_to_utf8($excerpt);
			$blog_name  = $this->_restore_to_utf8($blog_name);

			$excerpt    = $this->_cut_string($excerpt, 200);

			
			$url 	= $this->_createItemLink($item['itemid'],$blog);

			// Use UTF-8 charset for output
			header('Content-Type: text/xml');
			echo "<","?xml version='1.0' encoding='UTF-8'?",">\n";
			
			echo "<response>\n";
			echo "\t<error>0</error>\n";
			echo "\t<rss version='0.91'>\n";
			echo "\t\t<channel>\n";
			echo "\t\t\t<title>".htmlspecialchars($title, ENT_QUOTES)."</title>\n";
			echo "\t\t\t<link>".htmlspecialchars($url, ENT_QUOTES)."</link>\n";
			echo "\t\t\t<description>".htmlspecialchars($excerpt, ENT_QUOTES)."</description>\n";

			$query = 'SELECT url, blog_name, excerpt, title, UNIX_TIMESTAMP(timestamp) as timestamp FROM '.sql_table('plugin_tb').' WHERE tb_id='.intval($tb_id).' AND block = 0 ORDER BY timestamp DESC';
			$res = sql_query($query);
			while ($o = sql_fetch_object($res))
			{
				// No need to do conversion, because it is already UTF-8
				$data = array (
					'url' 		=> htmlspecialchars($o->url, ENT_QUOTES),
					'blogname' 	=> htmlspecialchars($this->_restore_to_utf8($o->blog_name), ENT_QUOTES),
					'timestamp' => strftime('%Y-%m-%d',$o->timestamp),
					'title' 	=> htmlspecialchars($this->_restore_to_utf8($o->title), ENT_QUOTES),
					'excerpt' 	=> htmlspecialchars($this->_restore_to_utf8($o->excerpt), ENT_QUOTES),
					'tburl' 	=> $this->getTrackBackUrl($tb_id)
				);
				
				echo "\n";
				echo "\t\t\t<item>\n";
				echo "\t\t\t\t<title>".$data['title']."</title>\n";
				echo "\t\t\t\t<link>".$data['url']."</link>\n";
				echo "\t\t\t\t<description>".$data['excerpt']."</description>\n";
				echo "\t\t\t</item>\n";
			}
			echo "\t\t</channel>\n";
			echo "\t</rss>\n";
			echo "</response>";
			exit;
		}
		else
		{
			$this->xmlResponse(_ERROR_NOSUCHITEM);
		}

	}



	/**************************************************************************************
	 * SENDING AND RECEIVING TRACKBACK PINGS
	 */

	/*
	 *  Send a Trackback ping to another website
	 */
	function sendPing($itemid, $title, $url, $excerpt, $blog_name, $ping_url)
	{
		$sendEncoding = 'UTF-8';
		
		// 1. Check some basic things
		if (!$this->canSendPing()) {
			return 'You\'re not allowed to send pings';
		}
		
		if ($this->getOption('SendPings') == 'no') {
			return 'Sending trackback pings is disabled';
		}
		
		if ($ping_url == '') {
			return 'No ping URL';
		}

		// 2. Check if protocol is correct http URL
		$parsed_url = parse_url($ping_url);

		if ($parsed_url['scheme'] != 'http' || $parsed_url['host'] == '')
			return 'Bad ping URL';

		$port = ($parsed_url['port']) ? $parsed_url['port'] : 80;

		// 3. Create contents
		if($sendEncoding != _CHARSET){
			$title = mb_convert_encoding($title, $sendEncoding, _CHARSET);
			$excerpt = mb_convert_encoding($excerpt, $sendEncoding, _CHARSET);
			$blog_name = mb_convert_encoding($blog_name, $sendEncoding, _CHARSET);
		}
		
		
		$content  = 'title=' . 	urlencode( $title );
		$content .= '&url=' . 		urlencode( $url );
		$content .= '&excerpt=' . 	urlencode( $excerpt );
		$content .= '&blog_name=' . urlencode( $blog_name );

		// 4. Prepare HTTP request
		$request  = 'POST ' . $parsed_url['path'];

		if ($parsed_url['query'] != '')
			$request .= '?' . $parsed_url['query'];
			
		$request .= " HTTP/1.1\r\n";
		$request .= "Accept: */*\r\n";
		$request .= "User-Agent: " . $this->userAgent . "\r\n";
		$request .= ( $port == 80 )?
								"Host: " . $parsed_url['host'] . "\r\n":
								"Host: " . $parsed_url['host'] . ":" . $port . "\r\n";
		$request .= "Cache-Control: no-cache\r\n";
		$request .= "Connection: Close\r\n";
		$request .= "Content-Length: " . strlen( $content ) . "\r\n";
		$request .= "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n";
		$request .= "\r\n";
		$request .= $content;

		$socket = fsockopen( $parsed_url['host'], $port, $errno, $errstr );
		if ( ! $socket )
			return 'Could not send ping: '.$errstr.' ('.$errno.')';

		// 5. Execute HTTP request
		fputs($socket, $request);

		// 6. Receive response
		$result = '';
		while (!feof($socket)) {
			$result .= fgets($socket, 4096);
		}
		
		fclose($socket);

		list($header, $body) = split("\r\n\r\n", $result, 2);
		preg_match("/HTTP\/1\.[0-1] ([0-9]+) ([^\r\n]*)\r?\n/", $header, $httpresp);
		$respCd = $httpresp[1];
		$respMsg = $httpresp[2];

		if( $respCd != 200 ){
			return 'An error occurred: HTTP Error: [' . $respCd . '] ' . $respMsg;
		}
		
		if( defined('NP_TRACKBACK_USE_XML_PARSER') && function_exists('xml_parser_create') ){
			$p = new NP_TrackBack_XMLParser();

			# remove invalid string
			$body = strstr($body, '<');
			$body = substr($body, 0, strrpos($body,'>') === false ? 0 : strrpos($body,'>') + 1 );

			$p->parse($body);
			$p->free();
			if( $p->isError ){
				$errorMessage = mb_convert_encoding($p->message, _CHARSET, 'UTF-8');
				return 'An error occurred: ' . htmlspecialchars($errorMessage, ENT_QUOTES);
			}
		} else {
			if ( strpos($DATA[1],'<error>0</error>') === false ){
				preg_match("/<message>(.*?)<\/message>/",$DATA[1],$error_message);
				if( $error_message[1] ){
					$errorMessage = mb_convert_encoding($error_message[1], _CHARSET);
					return 'An error occurred: '.htmlspecialchars($errorMessage, ENT_QUOTES);
				} else {
					return 'An error occurred: fatal error.';
				}
			}
		}
		
		return '';
	}

	/*
	 *  Handle a Trackback ping sent to this website
	 */
	function handlePing($tb_id = 0) {
		global $manager;
		
		// Defaults
		$spam       = false;
		$link       = false;
//			$block 	    = true;
		$block 	    = false;
		if ($tb_id == 0)
		$tb_id 		= intRequestVar('tb_id');
		
		$rss 		= requestVar('__mode') == 'rss';
		$enableLinkCheck = $this->isEnableLinkCheck($tb_id);
		$block = ( $enableLinkCheck ) ? true : false ;

		if (!$tb_id) {
			return 'TrackBack ID is missing (tb_id)';
		}
		
		if ((!$manager->existsItem($tb_id,0,0)) && ($this->getOption('CheckIDs') == 'yes')) {
			return _ERROR_NOSUCHITEM;
		}

		// 0. Check if we need to output the list as rss
		if ($rss) {
			$this->rssResponse($tb_id);
			return;
		}
		// check: accept pings.
		$blogId = getBlogIDFromItemID($tb_id);
		$isAcceptPing = $this->isAcceptTrackBack($tb_id);
		
		if (! $isAcceptPing)
			return 'Sorry, no trackback pings are accepted.';

		// 1. Get attributes
		$b =& $manager->getBlog($blogId);
		$url 		= requestVar('url');
		$title 		= requestVar('title');
		$excerpt 	= requestVar('excerpt');
		$blog_name 	= requestVar('blog_name');
		
		if( $url && preg_match('/https?:\/\/([^\/]+)/', $url, $matches) ){
			if( gethostbynamel($matches[1]) === FALSE )
				return 'URL is invalid (url)';
		} else {
			return 'URL is missing or invalid (url)';
		}

		// 2. Conversion of encoding...
		$encoding = $this->_detect_encoding($excerpt);
		
		if (_CHARSET != 'UTF-8'){
			$title = $this->_strip_controlchar(strip_tags(mb_convert_encoding($title, _CHARSET, $encoding)));
			$excerpt = $this->_strip_controlchar(strip_tags(mb_convert_encoding($excerpt, _CHARSET, $encoding)));
			$blog_name = $this->_strip_controlchar(strip_tags(mb_convert_encoding($blog_name, _CHARSET, $encoding)));
		}else{
			$title      = $this->_strip_controlchar($this->_convert_to_utf8($title, $encoding));
			$excerpt    = $this->_strip_controlchar($this->_convert_to_utf8($excerpt, $encoding));
			$blog_name  = $this->_strip_controlchar($this->_convert_to_utf8($blog_name, $encoding));

			$title      = $this->_decode_entities(strip_tags($title));
			$excerpt    = $this->_decode_entities(strip_tags($excerpt));
			$blog_name  = $this->_decode_entities(strip_tags($blog_name));
		}

		// 4. Save data in the DB
		$res = @sql_query('
			SELECT
				tb_id, block, spam
			FROM
				'.sql_table('plugin_tb').'
			WHERE
				url   = \''.sql_real_escape_string($url).'\' AND
				tb_id = \''.intval($tb_id).'\'
		');
		
		if (sql_num_rows($res) != 0)
		{
			// Existing TB, update it
			$rows = sql_fetch_assoc($res);
			$spam = ( $rows['block'] || $rows['spam'] ) ? true : false;
			$res = @sql_query('
				UPDATE
					'.sql_table('plugin_tb').'
				SET
					title     = \''.sql_real_escape_string($title).'\',
					excerpt   = \''.sql_real_escape_string($excerpt).'\',
					blog_name = \''.sql_real_escape_string($blog_name).'\',
					timestamp = '.mysqldate($b->getCorrectTime()).'
				WHERE
					url       = \''.sql_real_escape_string($url).'\' AND
					tb_id     = \''.sql_real_escape_string(intval($tb_id)).'\'
			');

			if (!$res) {
				return 'Could not update trackback data: '.sql_error();
			}
		}
		else
		{
			// spam block
			$res = @sql_query('SELECT id FROM '.sql_table('plugin_tb').' WHERE block = 1 and url = \''.sql_real_escape_string($url).'\'' );
			if (sql_num_rows($res) != 0) {
				// NP_Trackback has blocked tb !
				ACTIONLOG :: add(INFO, "Trackback: Duplicated Blocked Trackback [ignore] (itemid:$tb_id from: $url)");
				return 'Sorry, trackback ping is not accepted.';
			}
			
			// 4. SPAM check (for SpamCheck API 2 /w compat. API 1)
			$spamcheck = array (
				'type'  	=> 'trackback',
				'id'        	=> $tb_id,
				'title'		=> $title,
				'excerpt'	=> $excerpt,
				'blogname'  	=> $blog_name,
				'url'		=> $url,
				'return'	=> true,
				'live'   	=> true,
				
				/* Backwards compatibility with SpamCheck API 1*/
				'data'		=> $url . "\n" . $title . "\n" . $excerpt . "\n" . $blog_name . "\n" . serverVar('HTTP_USER_AGENT'),
				'ipblock'   => true,
			);
			
			$manager->notify('SpamCheck', array ('spamcheck' => & $spamcheck));
			
			if (isset($spamcheck['result']) && $spamcheck['result'] == true)
			{
				$spam = true;
			}
			
			// 5. Content check (TO DO)
			if($spam == false || $enableLinkCheck == 'ignore' )
			{
				if( $enableLinkCheck ){
					$contents = $this->retrieveUrl($url);
					
					$linkArray = $this->getPermaLinksFromText($contents);
					
					if( defined('NP_TRACKBACK_LINKCHECK_STRICT') )
						$itemLink = $this->_createItemLink($tb_id, $b);
					else
						$itemLink = $b->getURL();
					
					$itemLinkPat = '{^' . preg_quote($itemLink) .'}i';
					$itemLinkPat = str_replace('&','&(amp;)?', $itemLinkPat);
					
					foreach($linkArray as $l) {
						if(preg_match($itemLinkPat, $l)){
							ACTIONLOG :: add(INFO, "Trackback: LinkCheck OK. (link: $l pat: $itemLinkPat )");
							$link = true;
							break;
						}
					}
					if( ! $link ){
						$cnt = @count($linkArray);
						if( $enableLinkCheck == 'ignore' ){
							ACTIONLOG :: add(INFO, "Trackback: LinkCheck NG. [ignore] (itemid:$tb_id from: $url cnt: $cnt pat: $itemLinkPat)");
							return 'Sorry, trackback ping is not accepted.';
						} else {
							ACTIONLOG :: add(INFO, "Trackback: LinkCheck NG. [block] (itemid:$tb_id from: $url cnt: $cnt pat: $itemLinkPat");
						}
					}
				}
			}

			// 6. Determine if Trackback is safe...
			if ( $enableLinkCheck )
				$block = ($spam == true || $link == false);
			else
				$block = $spam == true ;
			
			$query = '
				INSERT INTO
					'.sql_table('plugin_tb').'
				SET
					tb_id     = \''.sql_real_escape_string(intval($tb_id)).'\',
					block     = \''.($block ? '1' : '0').'\',
					spam      = \''.($spam ? '1' : '0').'\',
					link      = \''.($link ? '1' : '0').'\',
					url       = \''.sql_real_escape_string($url).'\',
					title     = \''.sql_real_escape_string($title).'\',
					excerpt   = \''.sql_real_escape_string($excerpt).'\',
					blog_name = \''.sql_real_escape_string($blog_name).'\',
					timestamp = '.mysqldate($b->getCorrectTime()).'
			';
			
			$res = @sql_query($query);

			if (!$res) {
				return 'Could not save trackback data, possibly because of a double entry: ' . sql_error() . $query;
			}
		}

		// 7. Send notification e-mail if needed
		$notifyAddrs = $this->getOption('NotifyEmail');
		$notifyAddrs = ( $notifyAddrs ? $notifyAddrs . ';' : '')
						. $this->getBlogOption($blogId ,'NotifyEmailBlog');
					
		if ($notifyAddrs && $spam == false)
		{
			
			$vars = array (
				'tb_id'    => $tb_id,
				'url'      => $url,
				'title'    => $title,
				'excerpt'  => $excerpt,
				'blogname' => $blog_name
			);
			
//maybe not needed because japanese version has "mb_send_mail" in function notify
			
			$mailto_title = TEMPLATE::fill($this->notificationMailTitle, $vars);
			$mailto_msg   = TEMPLATE::fill($this->notificationMail, $vars);

			global $CONF, $DIR_LIBS;
			
			// make sure notification class is loaded
			if (!class_exists('notification'))
				include($DIR_LIBS . 'NOTIFICATION.php');
			
			$notify = new NOTIFICATION($notifyAddrs);
			$notify->notify($mailto_title, $mailto_msg , $CONF['AdminEmail']);
			
			if ($manager->pluginInstalled('NP_Cache')){
				$p =& $manager->getPlugin('NP_Cache');
				$p->setCurrentBlog($tb_id);
				$p->cleanItem($tb_id);
				$p->cleanArray(array('index'));
			}
		}

		if( $block )
			return 'Sorry, trackback ping is not accepted.';
		return '';
	}

	function xmlResponse($errorMessage = '')
	{
		header('Content-type: application/xml; charset=utf-8');
		echo "<"."?xml version='1.0' encoding='UTF-8'?".">\n";
		echo "<response>\n";

		if ($errorMessage){
			if (_CHARSET != 'UTF-8')
				$errorMessage = mb_convert_encoding($errorMessage, 'UTF-8');
			echo "<error>1</error>\n";
			echo "<message>".htmlspecialchars($errorMessage, ENT_QUOTES)."</message>\n";
		} else {
			echo "<error>0</error>\n";
		}

		echo "</response>";
		exit;
	}
	
	/*
	 * Check if member may send ping (check if logged in)
	 */
	function canSendPing() {
		global $member;
		return $member->isLoggedIn() || $this->xmlrpc;
	}


	function redirect($tb_id, $urlHash){
		global $CONF;
		$query = 'SELECT url FROM '.sql_table('plugin_tb').' WHERE tb_id='.intval($tb_id).' and md5(url)="'.$urlHash.'"';
		$res = sql_query($query);
		
		$url = $CONF['SiteURL'];
		
		if ($o = sql_fetch_object($res)) {
			$url = htmlspecialchars($o->url, ENT_QUOTES);
		}
		
		$url = stripslashes($url);
		$url = str_replace('&amp;','&',$url);
		$url = str_replace('&lt;','<',$url);
		$url = str_replace('&gt;','>',$url);
		$url = str_replace('&quot;','"',$url);
		
		header('Location: '.$url);
	}
	
	function getRequiredURL($itemid){
		global $manager;
		$blog = & $manager->getBlog(getBlogIDFromItemID($itemid));
		if( $this->isEnableLinkCheck($itemid) )
			return $this->_createItemLink($itemid, $blog);
		return null;
	}
	
	function isEnableLinkCheck($itemid){
		$blogid = getBlogIDFromItemID($itemid);
		
		switch( $this->getItemOption($itemid, 'isAcceptW/OLink') ){
			case 'default':
				$def = $this->getBlogOption($blogid, 'isAcceptW/OLinkDef');
				if($def == 'yes')
					return false;
				else
					return $def; // block or ignore
			case 'yes':
				return false;
			case 'no':
				return true;
			default :
				ACTIONLOG :: add(INFO, "Trackback: Unknown Option (itemid:$itemid, value:$val)");
				return false;
		}
	}
	
	function isAcceptTrackBack($itemid = null){
		$ret = false;
		if( $this->getOption('AcceptPing') == 'yes' ){
			$bid = null;
			if($itemid){
				$bid = getBlogIDFromItemID(intval($itemid));
			} else {
				global $blog;
				$bid = $blog->getID();
			}
			
			if( $this->getBlogOption($bid, "AllowTrackBack") == 'yes' ){
				if( $itemid ){
					$ret = ( $this->getItemOption(intval($itemid), 'ItemAcceptPing') == 'yes' ) ? true : false ;
				} else {
					$ret = true;
				}
			} else {
				$ret = false;
			}
		}
		return $ret;
	}
	

	/**************************************************************************************
	 * EVENTS
	 */

	function event_SendTrackback($data) {
		global $manager;
		
		// Enable sending trackbacks for the XML-RPC API, otherwise we would
		// get an error because the current user is not exactly logged in.
		$this->xmlrpc = true;
		
		$itemid = $data['tb_id'];
		$item = &$manager->getItem($itemid, 0, 0);
		if (!$item) return; // don't ping for draft & future
		if ($item['draft']) return;   // don't ping on draft items
		
		// gather some more information, needed to send the ping (blog name, etc)
		$blog =& $manager->getBlog(getBlogIDFromItemID($itemid));
		$blog_name 	= $blog->getName();
		
		$title      = $data['title'] != '' ? $data['title'] : $item['title'];
		$title 		= strip_tags($title);
		
		$excerpt    = $data['body'] != '' ? $data['body'] : $item['body'];
		$excerpt 	= strip_tags($excerpt);
		$excerpt    = $this->_cut_string($excerpt, 200);
		
		$CONF['ItemURL'] = preg_replace('/\/$/', '', $blog->getURL());
		//$url = createItemLink($itemid);
		$url = $this->_createItemLink($itemid, $blog);
		
		while (list(,$url) = each($data['urls'])) {
			$res = $this->sendPing($itemid, $title, $url, $excerpt, $blog_name, $url);
			if ($res) ACTIONLOG::add(WARNING, 'TrackBack Error:' . $res . ' (' . $url . ')');
		}
	}
	
	function event_RetrieveTrackback($data) {
		
		$res = sql_query('
		SELECT
		url,
		title,
		UNIX_TIMESTAMP(timestamp) AS timestamp
		FROM
		'.sql_table('plugin_tb').'
		WHERE
		tb_id = '.intval($data['tb_id']).' AND
		block = 0
		ORDER BY
		timestamp ASC
		');
		
		while ($row = sql_fetch_array($res)) {
			
			$trackback = array(
			'title' => $row['title'],
			'url'   => $row['url'],
			'ip'    => ''
			);
			
			$data['trackbacks'][] = $trackback;
		}
	}
/*
	function event_BookmarkletExtraHead($data) {
		global $NP_TB_URL;
		list ($NP_TB_URL,) = $this->getURIfromLink(requestVar('loglink'));
	}
*/
	function event_PrepareItemForEdit($data) {
//			if (!$this->getOption('AutoXMLHttp'))
		if ($this->getOption('AutoXMLHttp') == 'no')
		{
			// The space between body and more is to make sure we didn't join 2 words accidently....
			$this->larray = $this->autoDiscovery($data['item']['body'].' '.$data['item']['more']);
		}
	}

	/*
	 * After an item has been added to the database, send out a ping if requested
	 * (trackback_ping_url variable in request)
	 */
	function event_PostAddItem($data) {
		$this->pingTrackback($data);
	}

	function event_PreUpdateItem($data) {
		$this->pingTrackback($data);
	}

	/**
	 * Add trackback options to add item form/bookmarklet
	 */
	function event_AddItemFormExtras($data) {
	
//			global $NP_TB_URL;
		
		?>
			<h3>TrackBack</h3>
			<p>
<!--modify start+++++++++-->
<!--			<label for="plug_tb_url">TrackBack Ping URL:</label>
				<input type="text" value="<?php if (isSet($NP_TB_URL)) {echo $NP_TB_URL;} ?>" id="plug_tb_url" name="trackback_ping_url" size="60" />
-->
<!--modify end+++++++++-->
				<label for="plug_tb_url">TrackBack URL:</label><br />
				<textarea id="plug_tb_url" name="trackback_ping_url" cols="60" rows="5" style="font:normal xx-small Tahoma, Arial, verdana ;"></textarea>
<input type="button" name="btnAdd" value="<?php echo _TB_LIST_IT?>" onClick="AddStart()" />

	<br />

		<?php
//				if ($this->getOption('AutoXMLHttp'))
			if ($this->getOption('AutoXMLHttp') == 'yes')
			{
		?>
				<div id="tb_auto">
<input type="button" name="discoverit" value="Auto Discover" onclick="tbSetup();" />
					<img id='tb_busy' src='<?php echo $this->getAdminURL(); ?>busy.gif' style="display:none;" /><br />
				<div id="tb_auto_title"></div>
					<table border="1"><tbody id="tb_ping_list"></tbody></table>
					<input type="hidden" id="tb_url_amount" name="tb_url_amount" value="0" />
				</div>
				
		<?php
				$this->jsautodiscovery();
			}
		?>
			</p>
		<?php
	}

	/**
	 * Add trackback options to edit item form/bookmarklet
	 */
	function event_EditItemFormExtras($data) {
		global $CONF;
		?>
<!--					<input type="text" value="" id="plug_tb_url" name="trackback_ping_url" size="60" /><br />-->
			<h3>TrackBack</h3>
			<p>
				<label for="plug_tb_url">TrackBack URL:</label><br />
				<textarea id="plug_tb_url" name="trackback_ping_url" cols="60" rows="5" style="font:normal xx-small Tahoma, Arial, verdana ;"></textarea>
<input type="button" name="btnAdd" value="<?php echo _TB_LIST_IT?>" onClick="AddStart()" />

		<?php
//				if ($this->getOption('AutoXMLHttp'))
			if ($this->getOption('AutoXMLHttp') == 'yes')
			{
		?>


				<div id="tb_auto">
<input type="button" name="discoverit" value="Auto Discover" onclick="tbSetup();" />
					<img id='tb_busy' src='<?php echo $this->getAdminURL(); ?>busy.gif' style="display:none;" /><br />
				<div id="tb_auto_title"></div>
					<table border="1"><tbody id="tb_ping_list"></tbody></table>
					<input type="hidden" id="tb_url_amount" name="tb_url_amount" value="0" />
				</div>

		<?php
				$this->jsautodiscovery();
			}
			else
			{
				if (count($this->larray) > 0)
				{
		?>
				Auto Discovered Ping URL's:<br />
		<?php
					echo '<input type="hidden" name="tb_url_amount" value="'.count($this->larray).'" />';

					$i = 0;
					
					while (list($url, $title) = each ($this->larray))
					{
						if (_CHARSET != 'UTF-8') {
							$title = $this->_utf8_to_entities($title);
							$title = mb_convert_encoding($title, _CHARSET, 'UTF-8');
						}

						echo '<input type="checkbox" name="tb_url_'.$i.
							 '" value="'.$url.'" id="tb_url_'.$i.
							 '" /><label for="tb_url_'.$i.'" title="'.$url.'">'.$title.'</label><br />';
						
						$i++;
					}
				}
			}
		?>
			</p>
		<?php
	}

	/**
	 * Insert Javascript AutoDiscovery routines
	 */
	function jsautodiscovery()
	{
		global $CONF;
	
		?>
			<script type='text/javascript' src='<?php echo $this->getAdminURL(); ?>autodetect.php'></script>
		<?php
	}

	/**
	 * Ping all URLs
	 */
	function pingTrackback($data) {
		global $manager, $CONF;
		
		$ping_urls_count = 0;
		$ping_urls = array();
		$localflag = array();
		
		$ping_url = requestVar('trackback_ping_url');
/*
		if ($ping_url) {
			$ping_urls[0] = $ping_url;
			$ping_urls_count++;
		}
*/
		if (trim($ping_url)) {
			$ping_urlsTemp = array();
			$ping_urlsTemp = preg_split("/[\s,]+/", trim($ping_url));
			for($i=0;$i<count($ping_urlsTemp);$i++){
				$ping_urls[] = trim($ping_urlsTemp[$i]);
				$ping_urls_count++;
			}
		}

		$tb_url_amount = requestVar('tb_url_amount');
		for ($i=0;$i<$tb_url_amount;$i++) {
			$tb_temp_url = requestVar('tb_url_'.$i);
			if ($tb_temp_url) {
				$ping_urls[$ping_urls_count] = $tb_temp_url;
				$localflag[$ping_urls_count] = (requestVar('tb_url_'.$i.'_local') == 'on')? 1: 0;
				$ping_urls_count++;
			}
		}

		if ($ping_urls_count <= 0) {
			return;
		}

		$itemid = $data['itemid'];
		$item = &$manager->getItem($itemid, 0, 0);
		if (!$item) return; // don't ping for draft & future
		if ($item['draft']) return;   // don't ping on draft items

		// gather some more information, needed to send the ping (blog name, etc)
		$blog =& $manager->getBlog(getBlogIDFromItemID($itemid));
		$blog_name 	= $blog->getName();

		$title      = $data['title'] != '' ? $data['title'] : $item['title'];
		$title 		= strip_tags($title);

		$excerpt    = $data['body'] != '' ? $data['body'] : $item['body'];
		$excerpt 	= strip_tags($excerpt);
		$excerpt    = $this->_cut_string($excerpt, 200);

		$url 	= $this->_createItemLink($item['itemid'],$blog);

		// send the ping(s) (add errors to actionlog)
		for ($i=0; $i<count($ping_urls); $i++) {
			if( ! $localflag[$i] )
				$res = $this->sendPing($itemid, $title, $url, $excerpt, $blog_name, $ping_urls[$i]);
			else
				$res = $this->handleLocalPing($itemid, $title, $excerpt, $blog_name, $ping_urls[$i]);
			if ($res) ACTIONLOG::add(WARNING, 'TrackBack Error:' . $res . ' (' . $ping_urls[$i] . ')');
		}
	}

	/**************************************************************************************
	 * AUTO-DISCOVERY
	 */

	/*
	 * Auto-Discovery of TrackBack Ping URLs based on HTML story
	 */
	function autoDiscovery($text)
	{
		$links  = $this->getPermaLinksFromText($text);
		$result = array();

		for ($i = 0; $i < count($links); $i++)
		{
			list ($url, $title) = $this->getURIfromLink($links[$i]);
			
			if ($url != '')
				$result[$url] = $title;
		}
		
		return $result;
	}
	
	/*
	 * Auto-Discovery of TrackBack Ping URLs based on single link
	 */
	function getURIfromLink($link)
	{
		
		// Check to see if the cache contains this link
		$res = sql_query('SELECT url, title FROM '.sql_table('plugin_tb_lookup').' WHERE link=\''.sql_real_escape_string($link).'\'');

		if ($row = sql_fetch_array($res))
		{
			if ($row['title'] != '')
			{
				if (_CHARSET != 'UTF-8'){
					$row['title'] = mb_convert_encoding($row['title'], 'UTF-8', _CHARSET);
					$row['title'] = $this->_decode_entities($row['title']);
				}
				return array (
					$row['url'], $row['title']
				);
			}
			else
			{
				return array (
					$row['url'], $row['url']
				);
			}
		}
		
		// Retrieve RDF
		if (($rdf = $this->getRDFFromLink($link)) !== false)
		{
			// Get PING attribute
			if (($uri = $this->getAttributeFromRDF($rdf, 'trackback:ping')) !== false)
			{
				// Get TITLE attribute
				if (($title = $this->getAttributeFromRDF($rdf, 'dc:title')) !== false)
				{
					// Get CREATOR attribute
					if (($author = $this->getAttributeFromRDF($rdf, 'dc:creator')) !== false)
					{
						$title = $author. ": " . $title;
					}

					$uri   = $this->_decode_entities($uri);
					if (_CHARSET != 'UTF-8')
						$convertedTitle = mb_convert_encoding($title, _CHARSET, 'UTF-8');
					else
						$convertedTitle = $title;
/*
					// Store in cache
					$res = sql_query("INSERT INTO ".sql_table('plugin_tb_lookup')." (link, url, title) VALUES ('".sql_real_escape_string($link)."','".sql_real_escape_string($uri)."','".sql_real_escape_string($title)."')");
*/
					// Store in cache
					$res = sql_query("INSERT INTO ".sql_table('plugin_tb_lookup')." (link, url, title) VALUES ('".sql_real_escape_string($link)."','".sql_real_escape_string($uri)."','".sql_real_escape_string($convertedTitle)."')");
					$title = $this->_decode_entities($title);

					return array (
						$uri, $title
					);
				}
				else
				{
					$uri = html_entity_decode($uri, ENT_COMPAT);

					// Store in cache
					$res = sql_query("INSERT INTO ".sql_table('plugin_tb_lookup')." (link, url, title) VALUES ('".sql_real_escape_string($link)."','".sql_real_escape_string($uri)."','')");

					return array (
						$uri, $uri
					);
				}
			}
		}
		
		// Store in cache
		$res = sql_query("INSERT INTO ".sql_table('plugin_tb_lookup')." (link, url, title) VALUES ('".sql_real_escape_string($link)."','','')");

		return array ('', '');
	}

	/*
	 * Detect links used in HTML code
	 */
	function getPermaLinksFromText($text)
	{
		$links = array();
		
		if (preg_match_all('/<a +([^>]+)>/i', $text, $array, PREG_SET_ORDER))
		{
			$count = count($array);
			for ($i = 0; $i < $count; $i++)
			{
				if( preg_match('/https?:\/\/[-_.!~*\'()a-z0-9;\/?:@&=+$,%]+/i', $array[$i][1], $matches) )
					$links[$matches[0]] = 1;
			}
		}
		
		return array_keys($links);
	}

	/*
	 * Retrieve RDF code from external link
	 */
	function getRDFFromLink($link)
	{
		if ($content = $this->getContents($link))
		{
			preg_match_all('/(<rdf:RDF.*?<\/rdf:RDF>)/sm', $content, $rdfs, PREG_SET_ORDER);
			
			if (count($rdfs) > 1)
			{
				for ($i = 0; $i < count($rdfs); $i++)
				{
					if (preg_match('|dc:identifier="'.preg_quote($link).'"|ms',$rdfs[$i][1]))
					{
						return $rdfs[$i][1];
					}
				}
			}
			else
			{
				// No need to check the identifier
				return $rdfs[0][1];
			}
		}
		
		return false;
	}

	/**
	 * Retrieve the contents of an external (X)HTML document
	 */
	function getContents($link) {
	
		// Use cURL extention if available
		if (function_exists("curl_init") && $this->useCurl == 2)
		{
			// Make HEAD request
			$ch = curl_init();
			@curl_setopt($ch, CURLOPT_URL, $link);
			@curl_setopt($ch, CURLOPT_HEADER, true);
			@curl_setopt($ch, CURLOPT_NOBODY, true);
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			@curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
			@curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			@curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			@curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);

			$headers = curl_exec($ch);
			curl_close($ch);
			
			// Check if the link points to a (X)HTML document
			if (preg_match('/Content-Type: (text\/html|application\/xhtml+xml)/i', $headers))
			{
				return $this->retrieveUrl ($link);
			}
			
			return false;
		}
		else
		{
			return $this->retrieveUrl ($link);
		}
	}

	/*
	 * Get a single attribute from RDF
	 */
	function getAttributeFromRDF($rdf, $attribute)
	{
		if (preg_match('/'.$attribute.'="([^"]+)"/', $rdf, $matches))
		{
			return $matches[1];
		}
		
		return false;
	}

	/**************************************************************************************/
	/* Internal helper functions for dealing with external file retrieval                 */

	function retrieveUrl ($url) {
		$ua = ini_set('user_agent', $this->userAgent);
		if (function_exists('curl_init') && $this->useCurl > 0)
		{
			// Set options
			$ch = curl_init();
			@curl_setopt($ch, CURLOPT_URL, $url);
			@curl_setopt($ch, CURLOPT_HEADER, 1);
			@curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			@curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
			@curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			@curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
			@curl_setopt($ch, CURLOPT_TIMEOUT, 20);
			@curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
	
			// Retrieve response
			$raw  = curl_exec($ch);
			$info = curl_getinfo($ch);
		
			// Split into headers and contents
			$headers  = substr($raw, 0, $info['header_size']);
			$contents = substr($raw, $info['header_size']);

			curl_close($ch);
		}
		elseif ($fp = @fopen ($url, "rb"))
		{
//				$contents = fread($fp, 8192);
			$contents = '';
			while (!feof($fp)) {
				$contents .= fread($fp, 8192);
			}
			$headers  = '';
			
			fclose($fp);
		}
		ini_set('user_agent', $ua);
		
		// Next normalize the encoding to UTF8...
		$contents = $this->_convert_to_utf8_auto($contents, $headers);

		return $contents;
	}
	

	/**************************************************************************************/
	/* Internal helper functions for dealing with encodings and entities                  */

	var $entities_default = array (
		'&quot;'		=> '&#34;',
		'&amp;'   		=> '&#38;',
		'&apos;'  		=> '&#39;',
		'&lt;'    		=> '&#60;',
		'&gt;'    		=> '&#62;',
	);

	function _restore_to_utf8($contents)
	{
		if (_CHARSET != 'UTF-8')
		{
			$contents = mb_convert_encoding($contents, 'UTF-8', _CHARSET);
		}
		$contents = $this->_decode_entities(strip_tags($contents));
		return $contents;
	}
	function _detect_encoding($string)
	{
		if (function_exists('mb_convert_encoding')) {
			$encoding = (preg_match ("/;\s*charset=([^\n]+)/is", serverVar("CONTENT_TYPE"), $regs))?
				strtoupper(trim($regs[1])):
				'';

			if ( ($encoding !="") && ((mb_http_input("P") == "") || ( strtolower( ini_get("mbstring.http_input") ) == "pass")) ) {
				return $encoding;
			} else {
				$encoding = mb_detect_encoding($string, NP_TRACKBACK_ENCODING_DETECT_ORDER);
			}
			return ( $encoding ) ? $encoding : _CHARSET;
		}
		if (!ereg("[\x80-\xFF]", $string) && !ereg("\x1B", $string))
		return 'US-ASCII';
		
		if (!ereg("[\x80-\xFF]", $string) && ereg("\x1B", $string))
		return 'ISO-2022-JP';
		
		if (preg_match("/^([\x01-\x7F]|[\xC0-\xDF][\x80-\xBF]|[\xE0-\xEF][\x80-\xBF][\x80-\xBF])+$/", $string) == 1)
		return 'UTF-8';
		
		if (preg_match("/^([\x01-\x7F]|\x8E[\xA0-\xDF]|\x8F[xA1-\xFE][\xA1-\xFE]|[\xA1-\xFE][\xA1-\xFE])+$/", $string) == 1)
		return 'EUC-JP';
		
		if (preg_match("/^([\x01-\x7F]|[\xA0-\xDF]|[\x81-\xFC][\x40-\xFC])+$/", $string) == 1)
		return 'Shift_JIS';
		
		return 'ISO-8859-1';
	}

	function _convert_to_utf8($contents, $encoding)
	{
		$done = false;
		
		if(!$done && function_exists('mb_convert_encoding'))
		{
			
			if( function_exists('mb_substitute_character') ){
				@mb_substitute_character('none');
			}
			$result = @mb_convert_encoding($contents, 'UTF-8', $encoding );

			if ($result)
			{
				$contents = $result;
				$done = true;
			}
		}

		if (!$done && function_exists('iconv'))
		{
		
			$result = @iconv($encoding, 'UTF-8//IGNORE', $contents);

			if ($result)
			{
				$contents = $result;
				$done = true;
			}
		}
		return $contents;
	}
	
	function _convert_to_utf8_auto($contents, $headers = '')
	{
		/* IN:  string in unknown encoding, headers received during transfer
		 * OUT: string in UTF-8 encoding
		 */

		$str = substr($contents, 0, 4096);
		$len = strlen($str);
		$pos = 0;
		$out = '';
		
		while ($pos < $len)
		{
			$ord = ord($str[$pos]);
			
			if ($ord > 32 && $ord < 128)
				$out .= $str[$pos];
				
			$pos++;
		}

		// Detection of encoding, check headers
		if (preg_match ("/;\s*charset=([^\n]+)/is", $headers, $regs))
			$encoding = strtoupper(trim($regs[1]));

		// Then check meta inside document
		if (preg_match ("/;\s*charset=([^\"']+)/is", $out, $regs))
			$encoding = strtoupper(trim($regs[1]));
			
		// Then check xml declaration
		if (preg_match("/<\?xml.+encoding\s*=\s*[\"|']([^\"']+)[\"|']\s*\?>/i", $out, $regs))
			$encoding = strtoupper(trim($regs[1]));

		// Converts
		return $this->_convert_to_utf8($contents, $encoding);
	}
	
	function _decode_entities($string)
	{
		/* IN:  string in UTF-8 containing entities
		 * OUT: string in UTF-8 without entities
		 */
		 
		/// Convert all hexadecimal entities to decimal entities
		$string = preg_replace('/&#[Xx]([0-9A-Fa-f]+);/e', "'&#'.hexdec('\\1').';'", $string);
		
		global $_entities;
		// Deal with invalid cp1251 numeric entities
		$string = strtr($string, $_entities['cp1251']);

		// Convert all named entities to numeric entities
		$string = strtr($string, $this->entities_default);
		$string = strtr($string, $_entities['named']);

		// Convert all numeric entities to UTF-8
		$string = preg_replace('/&#([0-9]+);/e', "'&#x'.dechex('\\1').';'", $string);
		$string = preg_replace('/&#[Xx]([0-9A-Fa-f]+);/e', "NP_TrackBack::_hex_to_utf8('\\1')", $string);

		return $string;
	}

	function _hex_to_utf8($s){
		return entity::_hex_to_utf8($s);
	}

	function _utf8_to_entities($string)
	{
		/* IN:  string in UTF-8 encoding
		 * OUT: string consisting of only characters ranging from 0x00 to 0x7f,
		 *      using numeric entities to represent the other characters
		 */
		 
		$len = strlen ($string);
		$pos = 0;
		$out = '';
			
		while ($pos < $len)
		{
			$ascii = ord (substr ($string, $pos, 1));
			
			if ($ascii >= 0xF0)
			{
				$byte[1] = ord(substr ($string, $pos, 1)) - 0xF0;
				$byte[2] = ord(substr ($string, $pos + 1, 1)) - 0x80;
				$byte[3] = ord(substr ($string, $pos + 2, 1)) - 0x80;
				$byte[4] = ord(substr ($string, $pos + 3, 1)) - 0x80;

				$char_code = ($byte[1] << 18) + ($byte[2] << 12) + ($byte[3] << 6) + $byte[4];
				$pos += 4;
			}
			elseif (($ascii >= 0xE0) && ($ascii < 0xF0))
			{
				$byte[1] = ord(substr ($string, $pos, 1)) - 0xE0;
				$byte[2] = ord(substr ($string, $pos + 1, 1)) - 0x80;
				$byte[3] = ord(substr ($string, $pos + 2, 1)) - 0x80;

				$char_code = ($byte[1] << 12) + ($byte[2] << 6) + $byte[3];
				$pos += 3;
			}
			elseif (($ascii >= 0xC0) && ($ascii < 0xE0))
			{
				$byte[1] = ord(substr ($string, $pos, 1)) - 0xC0;
				$byte[2] = ord(substr ($string, $pos + 1, 1)) - 0x80;

				$char_code = ($byte[1] << 6) + $byte[2];
				$pos += 2;
			}
			else
			{
				$char_code = ord(substr ($string, $pos, 1));
				$pos += 1;
			}

			if ($char_code < 0x80)
				$out .= chr($char_code);
			else
				$out .=  '&#'. str_pad($char_code, 5, '0', STR_PAD_LEFT) . ';';
		}

		return $out;
	}

	function _utf8_to_javascript($string)
	{
		/* IN:  string in UTF-8 encoding
		 * OUT: string consisting of only characters ranging from 0x00 to 0x7f,
		 *      using javascript escapes to represent the other characters
		 */
		 
		$len = strlen ($string);
		$pos = 0;
		$out = '';
			
		while ($pos < $len)
		{
			$ascii = ord (substr ($string, $pos, 1));
			
			if ($ascii >= 0xF0)
			{
				$byte[1] = ord(substr ($string, $pos, 1)) - 0xF0;
				$byte[2] = ord(substr ($string, $pos + 1, 1)) - 0x80;
				$byte[3] = ord(substr ($string, $pos + 2, 1)) - 0x80;
				$byte[4] = ord(substr ($string, $pos + 3, 1)) - 0x80;

				$char_code = ($byte[1] << 18) + ($byte[2] << 12) + ($byte[3] << 6) + $byte[4];
				$pos += 4;
			}
			elseif (($ascii >= 0xE0) && ($ascii < 0xF0))
			{
				$byte[1] = ord(substr ($string, $pos, 1)) - 0xE0;
				$byte[2] = ord(substr ($string, $pos + 1, 1)) - 0x80;
				$byte[3] = ord(substr ($string, $pos + 2, 1)) - 0x80;

				$char_code = ($byte[1] << 12) + ($byte[2] << 6) + $byte[3];
				$pos += 3;
			}
			elseif (($ascii >= 0xC0) && ($ascii < 0xE0))
			{
				$byte[1] = ord(substr ($string, $pos, 1)) - 0xC0;
				$byte[2] = ord(substr ($string, $pos + 1, 1)) - 0x80;

				$char_code = ($byte[1] << 6) + $byte[2];
				$pos += 2;
			}
			else
			{
				$char_code = ord(substr ($string, $pos, 1));
				$pos += 1;
			}

			if ($char_code < 0x80)
				$out .= chr($char_code);
			else
				$out .=  '\\u'. str_pad(dechex($char_code), 4, '0', STR_PAD_LEFT);
		}

		return $out;
	}

	function _cut_string($string, $dl = 0) {
		$maxLength = $dl > 0 ? $dl : $this->getOption('defaultLength');
		
		if ($maxLength < 1)
			return $string;
		if (strlen($string) > $maxLength)
			$string = mb_strimwidth($string, 0, $maxLength, '...', _CHARSET);
		
		return $string;
	}
	
	function _strip_controlchar($string){
		$string = preg_replace("/[\x01-\x08\x0b\x0c\x0e-\x1f\x7f]+/","",$string);
		$string = str_replace("\0","",$string);
		return $string;
	}
	
	function checkTableVersion(){
		$res = sql_query("SHOW FIELDS from ".sql_table('plugin_tb') );
		$fieldnames = array();
		while ($co = sql_fetch_assoc($res)) {
			if($co['Field'] == 'block') return true;
		}
		return false;
	}
	
	/*---------------------------------------------------------------------------------- */
	/*   LOCAL                                                                           */
	/*---------------------------------------------------------------------------------- */
	/**
	  * Handle an incoming TrackBack ping and save the data in the database
	  */
	function handleLocalPing($itemid, $title, $excerpt, $blog_name, $ping_url){
		global $manager;
		$ping_url = trim($ping_url);
		
		if( preg_match("/^.+tb_id=([0-9]+)$/",$ping_url,$idnum) ){
			$tb_id = intval($idnum[1]);
		} elseif ( preg_match("/([0-9]+)\.trackback/",$ping_url,$idnum) ){
			$tb_id = intval($idnum[1]);
		} elseif ( preg_match("/itemid=([0-9]+)/",$ping_url,$idnum) ){
			$tb_id = intval($idnum[1]);
		}
	
		if ((!$manager->existsItem($tb_id,0,0)) && ($this->getOption('CheckIDs') == 'yes'))
			return _ERROR_NOSUCHITEM . "[ $tb_id ]";
			
		// save data in the DB
		$query = 'INSERT INTO ' . sql_table('plugin_tb_lc') . " (tb_id, from_id) VALUES ('".intval($tb_id)."','".intval($itemid)."')";
		$res = @sql_query($query);
		if (!$res)
			return 'Could not save trackback data, possibly because of a double entry: ' . sql_error();
	}

	/**
	  * Show the list of TrackBack pings for a certain Trackback ID
	  */
	function showLocalList($tb_id) {
		global $CONF, $manager;
		
		// create SQL query
		$query = 'SELECT t.from_id as from_id , i.ititle as ititle, i.ibody as ibody, i.itime as itime, i.iblog as iblog FROM '.sql_table('plugin_tb_lc').' as t, '.sql_table('item').' as i WHERE t.tb_id='.intval($tb_id) .' and i.inumber=t.from_id ORDER BY i.itime DESC';
		$res = sql_query($query);
		
		$vars = array(
			'tburl' => $this->getTrackBackUrl($tb_id)
		);
	
		// when no TrackBack pings are found
		if (!$res || sql_num_rows($res) == 0) {
			echo TEMPLATE::fill($this->getOption('tplLocalEmpty'), $vars);
			return;
		}
		
		// when TrackBack pings are found
		echo TEMPLATE::fill($this->getOption('tplLocalHeader'), $vars);
		
		while ($o = sql_fetch_object($res)) {
			$canDelete = $this->canDelete($tb_id);
			$data = array(
				'url' => createItemLink($o->from_id),
				'blogname' => htmlspecialchars(getBlogNameFromID($o->iblog)),
				'timestamp' => strftime('%Y-%m-%d',strtotime($o->itime)),
				'title' => htmlspecialchars($o->ititle),
				'excerpt' => htmlspecialchars(shorten(strip_tags($o->ibody),200,'...')),
				'delete' => $canDelete?'<a href="'. $manager->addTicketToUrl($CONF['ActionURL'].'?action=plugin&amp;name=TrackBack&amp;type=deletelc&amp;tb_id='.intval($tb_id).'&amp;from_id='.intval($o->from_id)).'">[delete]</a>':'',
				'tburl' => $this->getTrackBackUrl($tb_id),
				'commentcount'=> quickQuery('SELECT COUNT(*) as result FROM '.sql_table('comment').' WHERE citem=' . intval($o->from_id))
			);
			echo TEMPLATE::fill($this->getOption('tplLocalItem'), $data);
		}
		echo TEMPLATE::fill($this->getOption('tplLocalFooter'), $vars);
	}

	/**
	  * Delete a TrackBack item, redirect to referer
	  */
	function deleteLocal($tb_id, $from_id) {
		if (!$this->canDelete($tb_id))
			return 'You\'re not allowed to delete this trackback item';
		$query = 'DELETE FROM ' . sql_table('plugin_tb_lc') . " WHERE tb_id='" . intval($tb_id) . "' and from_id='" . intval($from_id) ."'";
		sql_query($query);
		return '';
	}
	
	function canDelete($tb_id) {
		global $member, $manager;
		
		if ( ! $member->isLoggedIn() ) return 0;
		
		$checkIDs = $this->getOption('CheckIDs');
		$itemExists =& $manager->existsItem($tb_id,0,0);
		
		if (($checkIDs == 'yes') || ($itemExists))
			return $member->canAlterItem($tb_id);
		else
			return $member->isAdmin();
	}
	
	/**************************************************************************************/
	/* Plugin API calls, for installation, configuration and setup                        */

	function getTableList()   { 		return array(sql_table("plugin_tb"), sql_table("plugin_tb_lookup"), sql_table('plugin_tb_lc')); }

	function getEventList()   { 		return array('QuickMenu','PostAddItem','AddItemFormExtras','EditItemFormExtras','PreUpdateItem','PrepareItemForEdit', 'BookmarkletExtraHead', 'RetrieveTrackback', 'SendTrackback', 'InitSkinParse'); }
	function getMinNucleusVersion() {	return 350; }

	function supportsFeature($feature) {
		switch($feature) {
			case 'SqlTablePrefix':
				return 1;
//				case 'HelpPage':
//					return 1;
			default:
				return 0;
		}
	}

	function event_QuickMenu(&$data) {
		global $member, $nucleus, $blogid;
		
		// only show to admins
		if (!$member->isLoggedIn()) return;

		array_push(
			$data['options'],
			array(
				'title' => 'Trackback',
				'url' => $this->getAdminURL(),
				'tooltip' => 'Manage your trackbacks'
			)
		);
	}
		
	function install() {
		$this->createOption('AcceptPing',  _TB_AcceptPing,'yesno','yes');
		$this->createOption('SendPings',   _TB_SendPings,'yesno','yes');
		$this->createOption('AutoXMLHttp', _TB_AutoXMLHttp, 'yesno', 'yes');
		$this->createOption('CheckIDs',	   _TB_CheckIDs,'yesno','yes');

		$this->createOption('tplHeader',   _TB_tplHeader, 'textarea', _TB_tplHeader_VAL);
		$this->createOption('tplEmpty',	   _TB_tplEmpty, 'textarea', _TB_tplEmpty_VAL);
		$this->createOption('tplItem',	   _TB_tplItem, 'textarea', _TB_tplItem_VAL);
		$this->createOption('tplFooter',   _TB_tplFooter, 'textarea', _TB_tplFooter_VAL);
		$this->createOption('tplLocalHeader',   _TB_tplLocalHeader, 'textarea', _TB_tplLocalHeader_VAL);
		$this->createOption('tplLocalEmpty',	   _TB_tplLocalEmpty, 'textarea', _TB_tplLocalEmpty_VAL);
		$this->createOption('tplLocalItem',	   _TB_tplLocalItem, 'textarea', _TB_tplLocalItem_VAL);
		$this->createOption('tplLocalFooter',   _TB_tplLocalFooter, 'textarea', _TB_tplLocalFooter_VAL);

		$this->createOption('tplTbNone',   _TB_tplTbNone, 'text', "No Trackbacks");
		$this->createOption('tplTbOne',    _TB_tplTbOne, 'text', "1 Trackback");
		$this->createOption('tplTbMore',   _TB_tplTbMore, 'text', "<%number%> Trackbacks");
		$this->createOption('dateFormat',  _TB_dateFormat, 'text', _TB_dateFormat_VAL);

		$this->createOption('NotifyEmail', _TB_NotifyEmail,'text','');
		$this->createOption('DropTable',   _TB_DropTable,'yesno','no');
		$this->createOption('HideUrl',_TB_HideUrl,'yesno','yes');
		$this->createOption('ajaxEnabled',_TB_ajaxEnabled,'yesno','no');

		$this->createItemOption('ItemAcceptPing',_TB_ItemAcceptPing,'yesno','yes');
		$this->createItemOption('isAcceptW/OLink',_TB_isAcceptWOLink,'select','default', _TB_isAcceptWOLink_VAL);

		$this->createBlogOption('NotifyEmailBlog', _TB_NotifyEmailBlog,'text','');
		$this->createBlogOption('isAcceptW/OLinkDef',_TB_isAcceptWOLinkDef,'select','block', _TB_isAcceptWOLinkDef_VAL);
		$this->createBlogOption('AllowTrackBack',_TB_AllowTrackBack,'yesno','yes');

		/* Create tables */
		sql_query("
			CREATE TABLE IF NOT EXISTS
				".sql_table('plugin_tb')."
			(
				`id`        INT(11)         NOT NULL       AUTO_INCREMENT,
				`tb_id`     INT(11)         NOT NULL,
				`url`       TEXT            NOT NULL,
				`block`     TINYINT(4)      NOT NULL,
				`spam`      TINYINT(4)      NOT NULL,
				`link`      TINYINT(4)      NOT NULL,
				`title`     TEXT,
				`excerpt`   TEXT,
				`blog_name` TEXT,
				`timestamp` DATETIME,
				
				PRIMARY KEY (`id`)
			)
		");
					
		sql_query("
			CREATE TABLE IF NOT EXISTS
				".sql_table('plugin_tb_lookup')."
			(
				`link`      TEXT            NOT NULL,
				`url`       TEXT            NOT NULL,
				`title`     TEXT,
				
				PRIMARY KEY (`link` (100))
			)
		");
		@sql_query('ALTER TABLE `' . sql_table('plugin_tb') . '` ADD INDEX `tb_id_block_timestamp_idx` ( `tb_id`, `block`, `timestamp` DESC )');
		@sql_query('CREATE TABLE IF NOT EXISTS ' . sql_table('plugin_tb_lc'). ' (tb_id int(11) not null, from_id int(11) not null, PRIMARY KEY (tb_id,from_id))');
	}

	function uninstall() {
		if ($this->getOption('DropTable') == 'yes') {
 			sql_query ('DROP TABLE '.sql_table('plugin_tb'));
			sql_query ('DROP TABLE '.sql_table('plugin_tb_lookup'));
			sql_query ("DROP table ".sql_table('plugin_tb_lc'));
		}
	}

	function init() {
		// include language file for this plugin
		$language = ereg_replace( '[\\|/]', '', getLanguageName());
		if (file_exists($this->getDirectory().'language/'.$language.'.php'))
			include_once($this->getDirectory().'language/'.$language.'.php');
  else
			include_once($this->getDirectory().'language/'.'english.php');
		$this->notificationMail = _TB_NORTIFICATION_MAIL_BODY;
		$this->notificationMailTitle = _TB_NORTIFICATION_MAIL_TITLE;
		
		$this->userAgent = 'NucleusCMS NP_TrackBack plugin ( '.$this->getVersion().' )';
	}
}

class NP_TrackBack_XMLParser {
	function NP_TrackBack_XMLParser(){
		$this->isError = false;
		$this->inTarget = false;
	}

	function parse($data){
		$rx = '/(<'.'?xml.*encoding=[\'"])(.*?)([\'"].*?'.'>)/m';
		if (preg_match($rx, $data, $m)) {
			$encoding = strtoupper($m[2]);
		} else {
			$encoding = mb_detect_encoding($data, NP_TRACKBACK_ENCODING_DETECT_ORDER);
		}
		
		if($encoding == "UTF-8" || $encoding == "ISO-8859-1") {
			// noting
		} else {
			$data = @mb_convert_encoding($data, "UTF-8", $encoding);
			$data = str_replace ( $m[0], $m[1].'UTF-8'.$m[3], $data);
			$encoding = 'UTF-8';
		}
		
		$this->parser = xml_parser_create($encoding);
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, "_open", "_close");
		xml_set_character_data_handler($this->parser, "_cdata");
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8').
		
		$this->words = array();
		xml_parse($this->parser, $data);
		$errcode = xml_get_error_code($this->parser);
	    if ( $errcode != XML_ERROR_NONE ) {
	    	$this->isError = true;
			$this->message = 'XML Parse Error: ' . xml_error_string($errcode) . ' in '. xml_get_current_line_number($this->parser);
	    }
		return $this->message;
	}

	function free(){
		xml_parser_free($this->parser);
	}

	function _open($parser, $name, $attribute){
		switch( $name ){
			case 'MESSAGE':
				$this->inTarget = 'MESSAGE';
				break;
			case 'ERROR':
				$this->inTarget = 'ERROR';
				break;
		}
	}

	function _close($parser, $name){
		if( $name == $this->inTarget ) $this->inTarget = null;
	}

	function _cdata($parser, $data){
		switch( $this->inTarget ){
			case 'MESSAGE':
				$this->message = trim($data);
				break;
			case 'ERROR':
				$this->isError = ($data ? true : false);
				break;
		}
	}
}
