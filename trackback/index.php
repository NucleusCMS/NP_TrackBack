<?php

	$strRel = '../../../'; 
	include($strRel . 'config.php');
	include($DIR_LIBS . 'PLUGINADMIN.php');
	include('template.php');
	
	
	$oPluginAdmin = new PluginAdmin('TrackBack');

	if ( !$member->isLoggedIn() )
	{
		$oPluginAdmin->start();
		echo '<p>' . _ERROR_DISALLOWED . '</p>';
		$oPluginAdmin->end();
		exit;
	}
	
	// Actions
	$action = requestVar('action');
	$aActionsNotToCheck = array(
		'',
		'ping',
	);
	if (!in_array($action, $aActionsNotToCheck)) {
		if (!$manager->checkTicket()) doError(_ERROR_BADTICKET);
	}

	$oPluginAdmin->start();
	
//modify start+++++++++
		$plug =& $oPluginAdmin->plugin;
		$tableVersion = $plug->checkTableVersion();

		// include language file for this plugin 
		$language = ereg_replace( '[\\|/]', '', getLanguageName()); 
		if (file_exists($plug->getDirectory().'language/'.$language.'.php')) 
			include_once($plug->getDirectory().'language/'.$language.'.php'); 
		else 
			include_once($plug->getDirectory().'language/'.'english.php');
//modify end+++++++++

	$mTemplate = new Trackback_Template();
	$mTemplate->set ('CONF', $CONF);
	$mTemplate->set ('plugid', $plug->getID());
	$mTemplate->set ('plugindirurl', $oPluginAdmin->plugin->getAdminURL());
	$mTemplate->template('templates/menu.html');
	echo $mTemplate->fetch();

	$oTemplate = new Trackback_Template();
	$oTemplate->set ('CONF', $CONF);
	$oTemplate->set ('plugindirurl', $oPluginAdmin->plugin->getAdminURL());
	$oTemplate->set ('ticket', $manager->_generateTicket());
	$ajaxEnabled = ($oPluginAdmin->plugin->getOption('ajaxEnabled') == 'yes') ? true : false;
	$oTemplate->set ('ajaxEnabled', $ajaxEnabled);
	
	$whereClause = '';
	if( ! $member->isAdmin() ){
		// where clause
		$res = sql_query('SELECT tblog FROM '.sql_table('team').' WHERE tadmin = 1 AND tmember = '.$member->getID() );
		$adminBlog = array();
		while ($row = mysql_fetch_array($res)){
			$adminBlog[] = $row[0];
		}
		if($adminBlog)
			$whereClause =  ' i.iblog in (' . implode(', ', $adminBlog) . ') ';
			
		if( $whereClause )
			$whereClause = ' AND ( i.iauthor = '.$member->getID().' OR ' . $whereClause . ' )';
		else
			$whereClause = ' AND i.iauthor = '.$member->getID();
	}
	//echo "<p>Debug: $whereClause<p>";
	
	$requiredAdminRights = array(
		'tableUpgrade',
		'blocked_clear',
		'blocked_spamclear',
	);
	if (in_array($action, $requiredAdminRights)) {
		if( ! $member->isAdmin() ){
			echo '<p>' . _ERROR_DISALLOWED . '</p>';
			echo '<p>Reason: ' . __LINE__ . '</p>';
			$oPluginAdmin->end();
			exit;
		}
	}
	
	$requiredItemEditRights = array(
		'block',
		'unblock',
		'delete',
	);
	if (in_array($action, $requiredItemEditRights)) {
		if( ! $member->isAdmin() ){
			$tb = intRequestVar('tb');
			$query = 'SELECT i.inumber FROM ' . sql_table('plugin_tb') . ' t, ' . sql_table('item') . ' i WHERE t.tb_id = i.inumber AND t.id = '. $tb . $whereClause ;
			$res = sql_query($query);
			if( ! @mysql_num_rows($res) ){
				echo '<p>' . _ERROR_DISALLOWED . '</p>';
				echo '<p>Reason: ' . __LINE__ . '</p>';
				$oPluginAdmin->end();
				exit;
			}
		}
	}

	switch($action) {

//modify start+++++++++
		case 'tableUpgrade':
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
			echo $q = "ALTER TABLE ".sql_table('plugin_tb')."
				 ADD `block` TINYINT( 4 ) NOT NULL AFTER `url` ,
				 ADD `spam` TINYINT( 4 ) NOT NULL AFTER `block` ,
				 ADD `link` TINYINT( 4 ) NOT NULL AFTER `spam` ,
				 CHANGE `url` `url` TEXT NOT NULL,
				 CHANGE `title` `title` TEXT NOT NULL,
				 CHANGE `excerpt` `excerpt` TEXT NOT NULL,
				 CHANGE `blog_name` `blog_name` TEXT NOT NULL,
				 DROP PRIMARY KEY,
				 ADD `id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST ;";
			$res = @sql_query($q);
			if (!$res){
				echo 'Could not alter table: ' . mysql_error();
			}else{
				$tableVersion = 1;
				$oTemplate->template('templates/updatetablefinished.html');
			}
			@sql_query('ALTER TABLE `' . sql_table('plugin_tb') . '` ADD INDEX `tb_id_block_timestamp_idx` ( `tb_id`, `block`, `timestamp` DESC )');
			break;
//modify end+++++++++

		case 'block':
			$tb = intRequestVar('tb');

			$res = sql_query ("
				UPDATE
					".sql_table('plugin_tb')."
				SET
					block = 1
				WHERE
					id = '".$tb."'
			");

			$action = requestVar('next');
			break;
			
		case 'blocked_clear':
			$res = sql_query ("DELETE FROM ".sql_table('plugin_tb')." WHERE block = 1");
			$action = requestVar('next');
			break;
			
		case 'blocked_spamclear':
			$res = sql_query ("DELETE FROM ".sql_table('plugin_tb')." WHERE block = 1 and spam = 1");
			$action = requestVar('next');
			break;

		case 'unblock':
			$tb = intRequestVar('tb');

			$res = sql_query ("
				UPDATE
					".sql_table('plugin_tb')."
				SET
					block = 0
				WHERE
					id = '".$tb."'
			");

			$action = requestVar('next');
			break;

		case 'delete':
			$tb = intRequestVar('tb');

			$res = sql_query ("
				DELETE FROM
					".sql_table('plugin_tb')."
				WHERE
					id = '".$tb."'
			");

			$action = requestVar('next');
			break;

		case 'sendping':
			$title     = requestVar('title');
			$url       = requestVar('url');
			$excerpt   = requestVar('excerpt');
			$blog_name = requestVar('blog_name');
			$ping_url  = requestVar('ping_url');		

			// No charset conversion needs to be done here, because
			// the charset used to receive the info is used to send
			// it...

			if ($ping_url) {
				$error = $oPluginAdmin->plugin->sendPing(0, $title, $url, $excerpt, $blog_name, $ping_url);
				
				if ($error) {
					echo '<b>TrackBack Error:' . $error . '</b>';
				}
			} 		
			
			$action = requestVar('next');
			break;
			
		case 'ping':
			$id  = intRequestVar('id');
			
			$usePathInfo = ($CONF['URLMode'] == 'pathinfo');
			if ($usePathInfo)
			@ include($strRel . 'fancyurls.config.php');
			
			global $manager;
			$itemData = $manager->getItem($id, 0, 0);
			
			if(is_array($itemData)){
				$blog =& $manager->getBlog($itemData['blogid']);
				$CONF['ItemURL'] = ($usePathInfo)? preg_replace('/\/$/', '', $blog->getURL()): $blog->getURL();
				$itemData['url'] = createItemLink($id);
				$itemData['excerpt'] = shorten(strip_tags($itemData['body'].$itemData['more']), 250, '...');
				$itemData['blogname'] = $blog->getName();
			}else{
				$itemData = array();
				$itemData['url'] = $CONF['IndexURL'];
				$itemData['blogname'] = $CONF['SiteName'];
			}
			$oTemplate->set('item', $itemData);
			
			$oTemplate->template('templates/ping.html');
			break; 			
	}

	// Pages 
	switch($action) {
		
		case 'help':
			$oTemplate->template('help.html');			
			break;

		case 'ping':
			$oTemplate->template('templates/ping.html');			
			break;

		case 'blocked':
		case 'all':	
			$rres = sql_query ("
				SELECT
					COUNT(*) AS count
				FROM
					".sql_table('plugin_tb')." AS t,
					".sql_table('item')." AS i
				WHERE
					t.tb_id = i.inumber AND
					t.block = " . (( $action == 'all') ? 0 : 1) . $whereClause );				
						
			if ($row = mysql_fetch_array($rres))
				$count = $row['count'];
			else
				$count = 0;
			$oTemplate->set('count', $count);

			if($ajaxEnabled){
				if( $action == 'all') 
					$oTemplate->template('templates/all_ajax.html');
				else			
					$oTemplate->template('templates/blocked_ajax.html');
			} else {
				$start  = intRequestVar('start') ? intRequestVar('start') : 0;
				$amount = intRequestVar('amount') ? intRequestVar('amount') : 25;

				$rres = sql_query ("
					SELECT
					i.ititle AS story,
					i.inumber AS story_id,
					t.id AS id,
					t.title AS title,
					t.blog_name AS blog_name,
					t.excerpt AS excerpt,
					t.url AS url,
					UNIX_TIMESTAMP(t.timestamp) AS timestamp,
					t.spam AS spam,
					t.link AS link
					FROM
					".sql_table('plugin_tb')." AS t,
					".sql_table('item')." AS i
					WHERE
					t.tb_id = i.inumber AND
					t.block = " . (( $action == 'all') ? 0 : 1) . $whereClause ."
					ORDER BY
					timestamp DESC
					LIMIT
					".$start.",".$amount);				
				
				$items = array();
				
				while ($rrow = mysql_fetch_array($rres)){
					$rrow['title'] 		= $oPluginAdmin->plugin->_cut_string($rrow['title'], 50);
					$rrow['title'] 		= $oPluginAdmin->plugin->_strip_controlchar($rrow['title']);
					$rrow['title'] 		= htmlspecialchars($rrow['title']);
					
					$rrow['blog_name'] 	= $oPluginAdmin->plugin->_cut_string($rrow['blog_name'], 50);
					$rrow['blog_name'] 	= $oPluginAdmin->plugin->_strip_controlchar($rrow['blog_name']);
					$rrow['blog_name'] 	= htmlspecialchars($rrow['blog_name']);
					
					$rrow['excerpt'] 	= $oPluginAdmin->plugin->_cut_string($rrow['excerpt'], 800);
					$rrow['excerpt'] 	= $oPluginAdmin->plugin->_strip_controlchar($rrow['excerpt']);
					$rrow['excerpt'] 	= htmlspecialchars($rrow['excerpt']);
					
					$rrow['url'] 		= htmlspecialchars($rrow['url'], ENT_QUOTES);
					$rrow['timestamp'] 		= htmlspecialchars($rrow['timestamp'], ENT_QUOTES);
					
					$blog = & $manager->getBlog(getBlogIDFromItemID($item['itemid']));
					$rrow['story_url'] = $oPluginAdmin->plugin->_createItemLink($rrow['story_id'], $blog);
					$rrow['story'] = htmlspecialchars(strip_tags($rrow['story']), ENT_QUOTES);
					
					$items[] = $rrow;
				}
				
				$oTemplate->set('amount', $amount);
				$oTemplate->set('start', $start);
				$oTemplate->set('items', $items);
				
				if( $action == 'all') 
					$oTemplate->template('templates/all.html');
				else			
					$oTemplate->template('templates/blocked.html');
			}
			break;
			
		case 'list':
			$id     = requestVar('id');
			$start  = intRequestVar('start') ? intRequestVar('start') : 0;
			$amount = intRequestVar('amount') ? intRequestVar('amount') : 25;

			$ires = sql_query ("
				SELECT
					i.ititle,
					i.inumber
				FROM
					".sql_table('item')." i 
				WHERE
					i.inumber = '".$id."'
			". $whereClause );
			
			if ($irow = mysql_fetch_array($ires))
			{
				$story['id']    = $id;
				$story['title'] = $irow['ititle'];

				$rres = sql_query ("
					SELECT
						COUNT(*) AS count
					FROM
						".sql_table('plugin_tb')." AS t
					WHERE
						t.tb_id = '".$id."' AND
						t.block = 0
				");				
							
				if ($row = mysql_fetch_array($rres))
					$count = $row['count'];
				else
					$count = 0;
					
				$rres = sql_query ("
					SELECT
						t.id AS id,
						t.title AS title,
						t.blog_name AS blog_name,
						t.excerpt AS excerpt,
						t.url AS url,
				        UNIX_TIMESTAMP(t.timestamp) AS timestamp
					FROM
						".sql_table('plugin_tb')." AS t
					WHERE
						t.tb_id = '".$id."' AND
						t.block = 0
					ORDER BY
						timestamp DESC
					LIMIT
						".$start.",".$amount."
				");				
				
				$items = array();
	
				while ($rrow = mysql_fetch_array($rres))
				{
					$rrow['title'] 		= $oPluginAdmin->plugin->_cut_string($rrow['title'], 50);
					$rrow['title'] 		= $oPluginAdmin->plugin->_strip_controlchar($rrow['title']);
					$rrow['title'] 		= htmlspecialchars($rrow['title']);
//					$rrow['title'] 		= _CHARSET == 'UTF-8' ? $rrow['title'] : $oPluginAdmin->plugin->_utf8_to_entities($rrow['title']);
	
					$rrow['blog_name'] 	= $oPluginAdmin->plugin->_cut_string($rrow['blog_name'], 50);
					$rrow['blog_name'] 	= $oPluginAdmin->plugin->_strip_controlchar($rrow['blog_name']);
					$rrow['blog_name'] 	= htmlspecialchars($rrow['blog_name']);
//					$rrow['blog_name'] 	= _CHARSET == 'UTF-8' ? $rrow['blog_name'] : $oPluginAdmin->plugin->_utf8_to_entities($rrow['blog_name']);
	
					$rrow['excerpt'] 	= $oPluginAdmin->plugin->_cut_string($rrow['excerpt'], 800);
					$rrow['excerpt'] 	= $oPluginAdmin->plugin->_strip_controlchar($rrow['excerpt']);
					$rrow['excerpt'] 	= htmlspecialchars($rrow['excerpt']);
//					$rrow['excerpt'] 	= _CHARSET == 'UTF-8' ? $rrow['excerpt'] : $oPluginAdmin->plugin->_utf8_to_entities($rrow['excerpt']);
	
					$rrow['url'] 		= htmlspecialchars($rrow['url'], ENT_QUOTES);
					$rrow['story'] = htmlspecialchars(strip_tags($rrow['story']), ENT_QUOTES);
					$items[] = $rrow;
				}
				
				$oTemplate->set ('amount', $amount);
				$oTemplate->set ('count', $count);
				$oTemplate->set ('start', $start);
				$oTemplate->set ('items', $items);
				$oTemplate->set ('story', $story);
				$oTemplate->template('templates/list.html');			
			}
			
			break;
							
		
		case 'index':
			$bres = sql_query ("
				SELECT
					bnumber AS bnumber,
					bname AS bname,
					burl AS burl
				FROM
					".sql_table('blog')."
				ORDER BY
					bname
			");
			
			$blogs = array();
			
			while ($brow = mysql_fetch_array($bres))
			{
				if( !$member->isTeamMember($brow['bnumber']) ) continue;
				$ires = sql_query ("
					SELECT
						i.inumber AS inumber,
					    i.ititle AS ititle,
					    COUNT(*) AS total
					FROM
						".sql_table('item')." AS i,
						".sql_table('plugin_tb')." AS t
					WHERE
						i.iblog = ".$brow['bnumber']." AND
						t.tb_id = i.inumber AND
						t.block = 0 ".$whereClause." 
					GROUP BY
						i.inumber
                    ORDER BY
                    	i.inumber DESC
				");				

				$items = array();

				while ($irow = mysql_fetch_array($ires))
				{
					$items[] = $irow;
				}

				$brow['items'] = $items;
				$blogs[] = $brow;
			}

			$oTemplate->set ('blogs', $blogs);
			$oTemplate->template('templates/index.html');
			break;

		default:
			//modify start+++++++++
			if(!$tableVersion){
				$oTemplate->template('templates/updatetable.html');
			}
			//modify end+++++++++
			break;
	}

	// Create the admin area page
	echo $oTemplate->fetch();
	
	echo '<div align="right">Powered by <a href="http://www.famfamfam.com/lab/icons/silk/">Silk icon</a></div>';
	$oPluginAdmin->end();	

