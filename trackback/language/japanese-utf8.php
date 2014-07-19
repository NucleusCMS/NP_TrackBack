<?php 
define('_TB_LIST_IT', '送信リストに追加');
define('_TB_DESCRIPTION', 'トラックバックの受送信を行います');

define('_TB_NORTIFICATION_MAIL_BODY', "<%blogname%> から ID:<%tb_id%> の記事に対してトラックバックを受信しました。 詳細は下記のとおりです:\n\nURL:\t<%url%>\nタイトル:\t<%title%>\n概要:\t<%excerpt%>\nブログ名:\t<%blogname%>");
define('_TB_NORTIFICATION_MAIL_TITLE', "トラックバックを受信しました ID:<%tb_id%>");

define('_TB_AcceptPing', 'トラックバックの受付をするか?');
define('_TB_SendPings', 'トラックバックの送信を可能にするか?');
define('_TB_AutoXMLHttp', 'autodiscovery機能(記事内のリンク先のTrackbackURLの自動検知)を使うか?');
define('_TB_CheckIDs', 'ping受付時に有効なitemidかどうかをチェックするか?');

define('_TB_tplHeader', 'TB一覧テンプレート(ヘッダ部)');
define('_TB_tplEmpty', 'TB一覧テンプレート(0件のとき)');
define('_TB_tplItem', 'TB一覧テンプレート(アイテム部)');
define('_TB_tplFooter', 'TB一覧テンプレート(フッタ部)');

define('_TB_tplHeader_VAL', "<div class=\"tb\">\n\t<div class=\"head\">トラックバック</div><%admin%>\n\n");
define('_TB_tplEmpty_VAL', "\t<div class=\"empty\">\n\t\tこのエントリにトラックバックはありません\n\t</div>\n\n");
define('_TB_tplItem_VAL', "\t<div class=\"item\">\n\t\t<div class=\"name\"><%name%></div>\n\t\t<div class=\"body\">\n\t\t\t<a href=\"<%url%>\"><%title%>:</a> <%excerpt%>\n\t\t</div>\n\t\t<div class=\"date\">\n\t\t\t<%date%>\n\t\t</div>\n\t</div>\n\n");
define('_TB_tplFooter_VAL', "\t<div class=\"info\">\n\t\tこの<a href=\"<%action%>\">トラックバックURL</a>を使ってこの記事にトラックバックを送ることができます。\n\t\tもしあなたのブログがトラックバック送信に対応していない場合には<a href=\"<%form%>\" onclick=\"window.open(this.href, 'trackback', 'scrollbars=yes,width=600,height=340,left=10,top=10,status=yes,resizable=yes'); return false;\">こちらのフォーム</a>からトラックバックを送信することができます。.\n\t</div>\n</div>");

define('_TB_tplLocalHeader', 'ローカルTB一覧テンプレート(ヘッダ部)');
define('_TB_tplLocalEmpty', 'ローカルTB一覧テンプレート(0件のとき)');
define('_TB_tplLocalItem', 'ローカルTB一覧テンプレート(アイテム部)');
define('_TB_tplLocalFooter', 'ローカルTB一覧テンプレート(フッタ部)');

define('_TB_tplLocalHeader_VAL', "<div class=\"tblocal\">\n\t<div class=\"head\">ローカルトラックバック</div>\n\n");
define('_TB_tplLocalEmpty_VAL', "");
define('_TB_tplLocalItem_VAL', "\t<div class=\"item\">\n\t\t<div class=\"body\">\n\t\t\t<%delete%> <a href=\"<%url%>\"><%title%></a>: <%excerpt%>\n\t\t</div>\n\t\t<div class=\"date\">\n\t\t\t<%timestamp%>\n\t\t</div>\n\t</div>\n\n");
define('_TB_tplLocalFooter_VAL', "\t</div>");

define('_TB_tplTbNone', 'TB数表示形式(0件)');
define('_TB_tplTbOne', 'TB数表示形式(1件)');
define('_TB_tplTbMore', 'TB数表示形式(2件以上)');
define('_TB_dateFormat', '日付の形式');
define('_TB_dateFormat_VAL', "%Y/%m/%d %H:%I");


define('_TB_ajaxEnabled', '管理画面でAjaxを有効にするか');
define('_TB_NotifyEmail', 'ping受付時のメール送信先(;で区切って複数入力可能)');
define('_TB_NotifyEmailBlog', 'ping受付時のメール送信先(;で区切って複数入力可能)');

define('_TB_DropTable', 'プラグインの削除時にデータを削除するか?');
define('_TB_HideUrl', '一覧表示の際に外部のURLをリダイレクトに変換するか?');
define('_TB_ItemAcceptPing', 'TBを受付するか?');
define('_TB_isAcceptWOLink', '言及リンクがなくてもTBを受付するか?');
define('_TB_isAcceptWOLinkDef', '言及リンクがなくてもTBを受付するか? (blogデフォルト)');
define('_TB_AllowTrackBack', 'このブログでTBを受付するか?');

define('_TB_isAcceptWOLink_VAL', 'ブログデフォルトに従う|default|はい|yes|いいえ|no');
define('_TB_isAcceptWOLinkDef_VAL', 'はい|yes|いいえ(保留)|block|いいえ(無視)|ignore');
