<!-- page menu -->
<div class="sidebar">
<dl class="sidebardl">
<dt>ナビゲーション</dt>

<!-- archive: navigation -->
<%if(skintype,archive)%>
<dd><a href="<%prevlink%>" title="Previous month">前の<%archivetype%></a></dd>
<dd><a href="<%nextlink%>" title="Next month">次の<%archivetype%></a></dd>
<dd><a href="<%todaylink%>" title="Today's entry">最新</a></dd>
<dd><a href="<%archivelink%>" title="Archives">過去ログ</a></dd>
<%endif%>

<!-- archivelist: navigation -->
<%if(skintype,archivelist)%>
<dd><a href="<%todaylink%>" title="Today's entry">最新</a></dd>
<dd><a href="<%archivelink%>" title="Archives">過去ログ</a></dd>
<%endif%>

<!-- error: navigation -->
<%if(skintype,error)%>
<dd><a href="<%todaylink%>" title="Today's entry">最新</a></dd>
<%endif%>

<!-- index: navigation -->
<%if(skintype,index)%>
<dd><a href="<%todaylink%>" title="Today's entry">最新</a></dd>
<dd><a href="<%archivelink%>" title="Archives">過去ログ</a></dd>
<%endif%>

<!-- item: navigation -->
<%if(skintype,item)%>
<dd><a href="<%nextlink%>" title="Previous item">前のスレッド</a></dd>
<dd><a href="<%prevlink%>" title="Next item">次のスレッド</a></dd>
<dd><a href="<%todaylink%>" title="Today's entry">最新</a></dd>
<dd><a href="<%archivelink%>" title="Archives">過去ログ</a></dd>
<%endif%>

<!-- member: navigation -->
<%if(skintype,member)%>
<dd><a href="<%todaylink%>" title="Today's entry">最新</a></dd>
<%endif%>

<!-- search: navigation -->
<%if(skintype,search)%>
<dd><a href="<%todaylink%>" title="Today's entry">最新</a></dd>
<dd><a href="<%archivelink%>" title="Archives">過去ログ</a></dd>
<%endif%>

<!-- admin link, only visible if logged in -->
<%if(loggedin)%>
<dd><a href="<%adminurl%>" title="Admin Area">管理エリア</a></dd>
<%endif%>
</dl>
</div>

<!-- category list, not on error or member page -->
<%if(skintype,error)%><%else%>
<%if(skintype,member)%><%else%>
<div class="sidebar">
<dl class="sidebardl">
<dt>カテゴリー</dt>
<%categorylist(nucbb/index)%>
</dl>
</div>
<%endif%><%endif%>

<!-- search form -->
<div class="sidebar">
<dl class="sidebardl">
<dt>検索</dt>
<dd><%searchform%></dd>
</dl>
</div>

<!-- login form -->
<div class="sidebar">
<dl class="sidebardl">
<dt>ログイン</dt>
<dd><%loginform%></dd>
</dl>
</div>

<!-- links -->
<div class="sidebar">
<dl class="sidebardl">
<dt>Links</dt>
<dd><a href="http://japan.nucleuscms.org" title="Nucleus CMS Home">Nucleus日本語版</a></dd>
</dl>
</div>

<!-- Powered by -->
<div class="sidebar">
<div class="sidebardl centerize">
<%nucleusbutton(images/nucleus.gif,85,31)%>
</div>
</div>