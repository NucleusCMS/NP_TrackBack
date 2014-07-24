<?php

	$strRel = '../../../'; 
	include($strRel . 'config.php');
	include($DIR_LIBS . 'PLUGINADMIN.php');
	include('template.php');

	// Send out Content-type
	header('Pragma: no-cache');	
	header("Content-Type: text/xml");
	sendContentType('text/xml', 'admin-trackback', _CHARSET);	

	$oPluginAdmin = new PluginAdmin('TrackBack');

	if ( ! $member->isLoggedIn() )
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
	);
	if (!in_array($action, $aActionsNotToCheck)) {
		if (!$manager->checkTicket()) doError(_ERROR_BADTICKET);
	}
	
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

	$oTemplate = new Trackback_Template();
	$oTemplate->set ('CONF', $CONF);
	$oTemplate->set ('plugindirurl', $oPluginAdmin->plugin->getAdminURL());
	$oTemplate->set ('ticket', $manager->_generateTicket());
		
	$whereClause = '';
	if( ! $member->isAdmin() ){
		// where clause
		$res = sql_query('SELECT tblog FROM '.sql_table('team').' WHERE tadmin = 1 AND tmember = '.$member->getID() );
		$adminBlog = array();
		while ($row = sql_fetch_array($res)){
			$adminBlog[] = $row[0];
		}
		if($adminBlog)
			$whereClause =  ' i.iblog in (' . implode(', ', $adminBlog) . ') ';
			
		if( $whereClause )
			$whereClause = ' AND ( i.iauthor = '.$member->getID().' OR ' . $whereClause . ' )';
		else
			$whereClause = ' AND i.iauthor = '.$member->getID();
	}
			
	$requiredItemEditRights = array(
		'dodelete',
		'doblock',
		'dounblock',
	);
	$safeids = array();
	if (in_array($action, $requiredItemEditRights)) {
		$ids = explode(',', requestVar('ids'));
		$safeids = array();
		foreach( $ids as $id ){
			$id = trim($id);
			if( is_numeric($id) )
				$safeids[] = $id;
		}	
		if( ! $member->isAdmin() ){
			$query = 'SELECT t.id  FROM ' . sql_table('plugin_tb') . ' t, ' . sql_table('item') . ' i WHERE t.tb_id = i.inumber AND t.id in ( '. implode(',', $safeids) . ' ) '. $whereClause ;
			$res = sql_query($query);
			$safeids = array();
			while ($row = sql_fetch_array($res)){
				$safeids[] = $row[0];
			}
		}
	}
	
	// Pages 
	switch($action) {
		
		case 'ajax':
			$type = requestVar('type') == 'all' ? 'all' : 'blocked' ;
			$filter['all'] = ' t.block = 0 ';
			$filter['blocked'] = ' t.block = 1 ';

			$start  = intRequestVar('offset') ? intRequestVar('offset') : 0;
			$amount = intRequestVar('page_size') ? intRequestVar('page_size') : 25;

			$colname = array();
			$colname['date'] = 'timestamp';
			$colname['item'] = 'story_id';
			$colname['title'] = 'title';
			
			$sort_col = requestVar('sort_col');
			$sort_col = $colname[$sort_col];
			if( !$sort_col ) $sort_col = $colname['date'];

			$sort_dir = ( requestVar('sort_dir') == 'ASC' ) ? 'ASC' : 'DESC';

			$rres = sql_query ("
			SELECT
			count(*) as count
			FROM
			".sql_table('plugin_tb')." AS t,
			".sql_table('item')." AS i
			WHERE
			t.tb_id = i.inumber AND
			".$filter[$type].$whereClause);
			$rrow = sql_fetch_array($rres);
			$count = $rrow['count'];
			
			$rres = sql_query ("
			SELECT
			i.ititle AS story,
			i.inumber AS story_id,
			t.id AS id,
			t.title AS title,
			t.blog_name AS blog_name,
			t.excerpt AS excerpt,
			t.url AS url,
			t.spam AS spam,
			UNIX_TIMESTAMP(t.timestamp) AS timestamp
			FROM
			".sql_table('plugin_tb')." AS t,
			".sql_table('item')." AS i
			WHERE
			t.tb_id = i.inumber AND
			".$filter[$type].$whereClause."
			ORDER BY
			".$sort_col." ".$sort_dir." 
			LIMIT
			".$start.",".$amount."
			");
			
			$items = array();
			
			while ($rrow = sql_fetch_array($rres))
			{
				$rrow['title'] 		= $oPluginAdmin->plugin->_cut_string($rrow['title'], 50);
				$rrow['title'] 		= $oPluginAdmin->plugin->_strip_controlchar($rrow['title']);
				$rrow['title'] 		= htmlspecialchars($rrow['title']);
				$rrow['title'] 		= preg_replace("/-+/","-",$rrow['title']);
				
				$rrow['blog_name'] 	= $oPluginAdmin->plugin->_cut_string($rrow['blog_name'], 50);
				$rrow['blog_name'] 	= $oPluginAdmin->plugin->_strip_controlchar($rrow['blog_name']);
				$rrow['blog_name'] 	= htmlspecialchars($rrow['blog_name']);
				$rrow['blog_name'] 		= preg_replace("/-+/","-",$rrow['blog_name']);
				
				$rrow['excerpt'] 	= $oPluginAdmin->plugin->_cut_string($rrow['excerpt'], 100);
				$rrow['excerpt'] 	= $oPluginAdmin->plugin->_strip_controlchar($rrow['excerpt']);
				$rrow['excerpt'] 	= htmlspecialchars($rrow['excerpt']);
				$rrow['excerpt'] 		= preg_replace("/-+/","-",$rrow['excerpt']);
				
				$rrow['url'] 		= htmlspecialchars($rrow['url'], ENT_QUOTES);
				
				$blog = & $manager->getBlog(getBlogIDFromItemID($rrow['story_id']));
				$rrow['story_url'] = $oPluginAdmin->plugin->_createItemLink($rrow['story_id'], $blog);
				$rrow['story'] = htmlspecialchars(strip_tags($rrow['story']), ENT_QUOTES);
				
				$items[] = $rrow;
			}
			
			$oTemplate->set ('amount', $amount);
			$oTemplate->set ('count', $count);
			$oTemplate->set ('start', $start);
			$oTemplate->set ('items', $items);
			$oTemplate->template('templates/response_'.$type.'.xml');			
			break;
			
		case 'dodelete':
			if( count($safeids) > 0 ){		
				$safeids = implode(',',$safeids);
				
				$res = sql_query(
						' DELETE FROM '
						. sql_table('plugin_tb')
						. ' WHERE id in (' . $safeids. ')'
				);
				$oTemplate->set ('message', $safeids . ' deleted.');
			} else {
				$oTemplate->set ('message', 'no rows deleted.');
			}
			
			$oTemplate->template('templates/response_dodelete.xml');
			break;
			
		case 'doblock':
			if( count($safeids) > 0 ){		
				$safeids = implode(',',$safeids);
				
				$res = sql_query(
						' UPDATE '
						. sql_table('plugin_tb')
						.' SET block = 1 '
						. ' WHERE id in (' . $safeids. ')'
				);
				$oTemplate->set ('message', $safeids . ' blocked.');
			} else {
				$oTemplate->set ('message', 'no rows blocked.');
			}
			
			$oTemplate->template('templates/response_doblock.xml');
			break;
						
		case 'dounblock':
			if( count($safeids) > 0 ){		
				$safeids = implode(',',$safeids);
				
				$res = sql_query(
						' UPDATE '
						. sql_table('plugin_tb')
						.' SET block = 0 '
						. ' WHERE id in (' . $safeids. ')'
				);
				$oTemplate->set ('message', $safeids . ' unblocked.');
			} else {
				$oTemplate->set ('message', 'no rows unblocked.');
			}
			
			$oTemplate->template('templates/response_dounblock.xml');
			break;
	}

	// Create the admin area page
	echo $oTemplate->fetch();
	
