<?php
	$strRel = '../../../'; 
	include($strRel . 'config.php');
	
	global $manager, $CONF;
	$action = $manager->addTicketToUrl($CONF['ActionURL'] . '?action=plugin&name=TrackBack&type=detect')	
?>
	var xmlhttp = false;
	var inProgress = false;
	
	var TrackbackAction = "<?php echo $action; ?>";
	var TrackbackSource = new Array;
	var TrackbackName   = new Array;
	var TrackbackURL    = new Array;
	
	var LookupTable     = new Array;
	var Lookup			= '';
	var countTotal			= 0;
	
	var regexp = /href\s*=\s*([\"\'])(http:[^\"\'>]+)([\"\'])/ig;
		
		
	function tbParseLinks ()
	{
		oinputbody = document.getElementById('inputbody');
		oinputmore = document.getElementById('inputmore');
		full = oinputbody.value + ' ' + oinputmore.value;

		while (vArray = regexp.exec(full)) 
		{
			unused = true;
			
			if (Lookup == vArray[2])
				unused = false;

			for (var i = 0; i < LookupTable.length; i++)
				if (LookupTable[i] == vArray[2])
					unused = false;

			for (var i = 0; i < TrackbackSource.length; i++) 
				if (TrackbackSource[i] == vArray[2])
					unused = false;
			
			if (unused == true)
				LookupTable.push(vArray[2]);
		}
	}
	
	function tbAutoDetect()
	{
		if (LookupTable.length > 0)
		{
			tbBusy(true);

			if (!inProgress)
			{
				// We have something to do and the connection is free
				Lookup = LookupTable.shift();
				inProgress = true;
	
				// The reason we use GET instead of POST is because
				// Opera does not properly support setting headers yet,
				// which is a requirement for using POST.
				xmlhttp.open("GET", TrackbackAction + "&tb_link=" + escape(Lookup), true);
				xmlhttp.onreadystatechange = tbStateChange;
				xmlhttp.send('');
			}
			else
			{
				// Still busy... simply wait until next turn
			}
		}
		else
		{
			// Nothing to do, check back later...
			if (Lookup == '')
			{
				tbBusy(false);
			}
		}
	}

	function tbStateChange ()
	{
		if (inProgress == true && xmlhttp.readyState == 4 && xmlhttp.status == 200) 
		{
			eval (xmlhttp.responseText);
			inProgress = false;
			Lookup = '';
		}
	}

	function tbBusy(toggle)
	{

		if (toggle)
		{
				document.forms[0].discoverit.style.color = "#888888";
				document.forms[0].discoverit.style.fontWeight="bold";
				document.forms[0].discoverit.value = "  Loading ....";
		}
		else
		{
				document.forms[0].discoverit.style.color = "#888888";
				document.forms[0].discoverit.style.fontWeight="bold";
				document.forms[0].discoverit.value = "  D o n e !  ";
		}

		o = document.getElementById('tb_busy');
		
		if (o)
		{
			if (toggle)
				o.style.display = '';
			else
				o.style.display = 'none'
		}
	}

	function tbDone(source, url, name)
	{
		TrackbackSource.push(source);
		TrackbackURL.push(url);
		TrackbackName.push(name);
			
//		var parent = document.getElementById('tb_auto');
		var amount = document.getElementById('tb_url_amount');
		var subtitle = document.getElementById('tb_auto_title');
		var listtable = document.getElementById('tb_ping_list');

		if (url != '')
		{
//			count = parseInt(amount.value);
			count = countTotal;

			mycurrent_row=document.createElement("TR");

			checkbox = document.createElement("input");
			checkbox.type = 'checkbox';
			checkbox.name = "tb_url_" + count;
			checkbox.id = "tb_url_" + count;
			checkbox.value = url;
			checkbox.defaultChecked = true;

			label =	document.createElement("label"); 
			label.htmlFor = "tb_url_" + count;
			label.title = source;
			
			text = document.createTextNode(name);
			label.appendChild(text);
			
			
//			br = document.createElement("br"); 

//			subtitle.innerHTML = "Auto Discovered Ping URL's:";
//			parent.appendChild(checkbox);
//			parent.appendChild(label);

			mycurrent_cell=document.createElement("TD");
			mycurrent_cell.appendChild(checkbox);
			mycurrent_row.appendChild(mycurrent_cell);
			mycurrent_cell=document.createElement("TD");
			mycurrent_cell.appendChild(label);
			mycurrent_row.appendChild(mycurrent_cell);
			
			mycurrent_row.appendChild(mycurrent_cell);


			if(url.indexOf("<?php echo $CONF['IndexURL'];?>",0) != -1)
			{
				//local?
				checkboxL = document.createElement("input");
				checkboxL.type = 'checkbox';
				checkboxL.name = "tb_url_" + count + "_local";
				checkboxL.id = "tb_url_" + count + "_local";
				checkboxL.defaultChecked = true;

				labelL =	document.createElement("label"); 
				labelL.htmlFor = "tb_url_" + count + "_local";
				labelL.title = "local?";

				text = document.createTextNode("local?");
				labelL.appendChild(text);
//				parent.appendChild(checkboxL);
//				parent.appendChild(labelL);
				mycurrent_cell=document.createElement("TD");
				mycurrent_cell.appendChild(checkboxL);
				mycurrent_cell.appendChild(labelL);
				mycurrent_row.appendChild(mycurrent_cell);

			}
			else
			{
				mycurrent_cell=document.createElement("TD");
				mycurrent_row.appendChild(mycurrent_cell);
			}
//			parent.appendChild(br);
			listtable.appendChild(mycurrent_row);

//			amount.value = count + 1;
			countTotal++;
			amount.value = countTotal;
		}
		else
		{
			subtitle.innerHTML = "No Trackbak URLs.";
		}
	}

	function tbSetup() 
	{
		try 
		{
			xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		} 
		catch (e) 
		{
			try 
			{
				xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
			} 
			catch (e) 
			{
				xmlhttp = false;
			}
		}

		if (!xmlhttp && typeof XMLHttpRequest!='undefined') 
		{
			xmlhttp = new XMLHttpRequest();
		}
		
		setInterval ('tbParseLinks();', 500);
		setInterval ('tbAutoDetect();', 500);
		
		if (window.onloadtrackback)
			window.onloadtrackback();				
	}

	function AddStart()
	{
		var strString = "";
		strString = document.forms[0].trackback_ping_url.value;
		strArray = strString.split("\n");
				for (var i = 0; i < strArray.length; i++)
				{
					strTemp = trim(strArray[i]);
					if (strTemp != "" && strTemp.match(/^http/))
					{
						tbDone(strTemp,strTemp,strTemp);
					}
				}
		document.forms[0].trackback_ping_url.value = '';
	}

	function trim(string) 
	{ 
		return string.replace(/(^\s*)|(\s*$)/g,''); 
	}
