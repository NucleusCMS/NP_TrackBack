<?php 
define('_TB_LIST_IT', 'List it');
define('_TB_DESCRIPTION', 'Send trackbacks to other weblogs and receive tracbacks from others.');

define('_TB_NORTIFICATION_MAIL_BODY', "Your weblog received a new trackback from <%blogname%> for ID <%tb_id%>. Below are the full details:\n\nURL:\t<%url%>\nTitle:\t<%title%>\nExcerpt:\t<%excerpt%>\nBlogname:\t<%blogname%>");
define('_TB_NORTIFICATION_MAIL_TITLE', "New Trackback received for ID <%tb_id%>");

define('_TB_AcceptPing', 'Accept pings');
define('_TB_SendPings', 'Allow sending pings');
define('_TB_AutoXMLHttp', 'Auto-detect Trackback URLs as you type');
define('_TB_CheckIDs', 'Only allow valid itemids as trackback-ids');

define('_TB_tplHeader', 'Header');
define('_TB_tplEmpty', 'Empty');
define('_TB_tplItem', 'Item');
define('_TB_tplFooter', 'Footer');

define('_TB_tplHeader_VAL', "<div class=\"tb\">\n\t<div class=\"head\">Trackback</div><%admin%>\n\n");
define('_TB_tplEmpty_VAL', "\t<div class=\"empty\">\n\t\tThere are currently no trackbacks for this item.\n\t</div>\n\n");
define('_TB_tplItem_VAL', "\t<div class=\"item\">\n\t\t<div class=\"name\"><%name%></div>\n\t\t<div class=\"body\">\n\t\t\t<a href=\"<%url%>\"><%title%>:</a> <%excerpt%>\n\t\t</div>\n\t\t<div class=\"date\">\n\t\t\t<%date%>\n\t\t</div>\n\t</div>\n\n");
define('_TB_tplFooter_VAL', "\t<div class=\"info\">\n\t\tUse this <a href=\"<%action%>\">TrackBack url</a> to ping this item (right-click, copy link target).\n\t\tIf your blog does not support Trackbacks you can manually add your trackback by using <a href=\"<%form%>\" onclick=\"window.open(this.href, 'trackback', 'scrollbars=yes,width=600,height=340,left=10,top=10,status=yes,resizable=yes'); return false;\">this form</a>.\n\t</div>\n</div>");

define('_TB_tplLocalHeader_VAL', "<div class=\"tblocal\">\n\t<div class=\"head\">Local Trackback</div>\n\n");
define('_TB_tplLocalEmpty_VAL', "");
define('_TB_tplLocalItem_VAL', "\t<div class=\"item\">\n\t\t<div class=\"body\">\n\t\t\t<%delete%> <a href=\"<%url%>\"><%title%></a>: <%excerpt%>\n\t\t</div>\n\t\t<div class=\"date\">\n\t\t\t<%timestamp%>\n\t\t</div>\n\t</div>\n\n");
define('_TB_tplLocalFooter_VAL', "\t</div>");

define('_TB_tplLocalHeader', 'Header (Local)');
define('_TB_tplLocalEmpty', 'Empty (Local)');
define('_TB_tplLocalItem', 'Item (Local)');
define('_TB_tplLocalFooter', 'Footer (Local)');

define('_TB_tplTbNone', 'Trackback count (none)');
define('_TB_tplTbOne', 'Trackback count (one)');
define('_TB_tplTbMore', 'Trackback count (more)');

define('_TB_dateFormat', 'Date format');
define('_TB_dateFormat_VAL', "%e/%m/%g");

define('_TB_ajaxEnabled', 'Enable Ajax ?');
define('_TB_NotifyEmail', 'Which e-mail address to send these notification to?');
define('_TB_NotifyEmailBlog', 'Which e-mail address to send these notification to?');

define('_TB_DropTable', 'Clear the database when uninstalling');
define('_TB_HideUrl', 'Hide external URL');
define('_TB_ItemAcceptPing', 'Accept pings');
define('_TB_isAcceptWOLink', 'Accept pings w/o link ?');
define('_TB_isAcceptWOLinkDef', 'Accept pings w/o link ? (blog default)');
define('_TB_AllowTrackBack', 'Accept pings to this blog');

define('_TB_isAcceptWOLink_VAL', 'default|default|yes|yes|no|no');
define('_TB_isAcceptWOLinkDef_VAL', 'yes|yes|no (block)|block|no (ignore)|ignore');
