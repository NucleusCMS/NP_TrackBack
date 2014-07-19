<?php
	$strRel = '../../../'; 
	include($strRel . 'config.php');

?>
	var TrackbackAction = "<?php echo $CONF['ActionURL'];?>";
	var tb_id = "<?php echo intRequestVar('tb_id')?>";
	var tb_amount = "<?php echo intRequestVar('amount')?>";
	var xmlhttp = false;

	function resttbStart() 
	{
		document.getElementById("tbhidenavi").style.display = "block";
		document.getElementById("tbshownavi").style.display = "none";
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
		
		if (xmlhttp)
		{
			loadXMLDoc();
		}
		
	}

	function loadXMLDoc()
	{

		var url =  TrackbackAction + '?action=plugin&name=TrackBack&type=left&tb_id=' + tb_id + '&amount=' + tb_amount;
		
		xmlhttp.onreadystatechange=xmlhttpChange
		xmlhttp.open("GET",url,true)
		xmlhttp.send('')
	}

	function xmlhttpChange()
	{
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) 
		{
			var result = document.getElementById("resttb");
			result.innerHTML = xmlhttp.responseText;
		}
	}

	function hideresttb()
	{
		var result = document.getElementById("resttb");
		result.innerHTML = "";
		
		document.getElementById("tbhidenavi").style.display = "none";
		document.getElementById("tbshownavi").style.display = "block";
		
	}
