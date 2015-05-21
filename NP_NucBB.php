<?php
// NP_NucBB -- make a BB using Nucleus core features
// author Andy http://lab.matsubarafamily.com/
// licensed uder GPL v2.
// plugin needs to work on Nucleus versions <=2.0 as well

class NP_NucBB extends NucleusPlugin {

	/*
	 * Edit templates here (they couldn't be placed in plugin options. maybe later)
	 */
	var $userinfo = NULL;
	var $cacheditem = NULL;
	var $currentblog = NULL;
	var $commentid;

	function getName() { 		return 'NucBB'; }
	function getAuthor()  {		return 'Andy'; }
	function getURL() {			return ''; }
	function getVersion() {		return '0.971'; }
	function getMinNucleusVersion() {
		return 220;
	}

	function supportsFeature($what) {
		switch($what)
		{
			case 'SqlTablePrefix':
				return 1;
			default:
				return 0;
		}
	}
	
	function init() {
		// include language file for this plugin
		$language = str_replace( array('\\','/'), '', getLanguageName());
		if (file_exists($this->getDirectory().$language.'.php'))
			include_once($this->getDirectory().$language.'.php');
		include_once($this->getDirectory().'english.php');
		if (!$_SERVER["REQUEST_URI"])
			$_SERVER["REQUEST_URI"] = serverVar("SCRIPT_NAME") . "?" . serverVar("QUERY_STRING"); 
	}


	function getDescription() { 
		return 'Simple BBS using Nucleus item and comment feature.';
	}

	function getTableList() {	return array( sql_table('plugin_nucbb'), sql_table('plugin_nucbb_blogs'), sql_table('plugin_nucbb_comments') ); }
	function getEventList() {
		return array('PostAddComment', 
					'PostPluginOptionsUpdate', 
					'PostDeleteBlog', 
					'PostDeleteItem', 
					'PostDeleteComment', 
					'PreComment');
	}
	
	function hasAdminArea() {
		return 0;
	}

	function install() {
		global $member;
		sql_query('CREATE TABLE IF NOT EXISTS ' . sql_table('plugin_nucbb'). ' (item_id int(11) not null, blog_id int(11) not null, url varchar(100), email varchar(50), username varchar(50) not null, password varchar(50), originaldate datetime, PRIMARY KEY (item_id))');
		sql_query('CREATE TABLE IF NOT EXISTS ' . sql_table('plugin_nucbb_blogs'). ' (blog_id int(11) not null)');
		sql_query('CREATE TABLE IF NOT EXISTS ' . sql_table('plugin_nucbb_comments'). ' (comment_id int(11) not null, mail varchar(50), password varchar(50), imagelink varchar(255), PRIMARY KEY (comment_id))');
		if (!sql_query('SELECT imagelink FROM '.sql_table('plugin_nucbb_comments').' WHERE 1=1'))
			sql_query('ALTER TABLE '.sql_table('plugin_nucbb_comments').' ADD imagelink varchar(255)');
		list($usec, $sec) = explode(' ', microtime());
		mt_srand((float) $sec + ((float) $usec * 100000));
		$password = strval(mt_rand());
		if (!MEMBER::exists('NucBBUser')) {
			MEMBER::create('NucBBUser', 'NucBB User', $password, $member->email, $member->url, 0, 0, "");
		}

		$formnotlogined = <<<FORMNOTLOGINED
<form action="<%action%>" enctype="multipart/form-data" method="post">
	<div class="commentform">
		<label for="nucbb_user"><%const(_NUCBB_NAME)%></label>
		<input type="text" name="nucbbuser" value="<%username%>" id="nucbb_user" class="formfield"  <%disabled%> /><br />
		<label for="nucbb_mail"><%const(_NUCBB_MAIL)%></label>
		<input type="text" name="nucbbemail" value="<%mail%>" id="nucbb_mail" class="formfield" <%disabled%> /><br />
		<label for="nucbb_url"><%const(_NUCBB_URL)%></label>
		<input type="text" name="nucbburl" value="<%url%>" id="nucbb_url"  class="formfield" <%disabled%> /><br />
		<label for="nucbb_subject"><%const(_NUCBB_SUBJECT)%></label>
		<input type="text" name="nucbbsubject" value="<%title%>" id="nucbb_subject" <%disabled%> class="formfield" /><br />
		<label for="nucbb_pass"><%const(_NUCBB_PASSWORD)%></label>
		<input type="password" name="nucbbpass" value="" id="nucbb_pass" class="formothers" />
		<input type="checkbox" value="1" name="nucbbremember" <%remember%> <%disabled%> /><%const(_NUCBB_REMEMBER)%><br />
		<label for="nucbb_contents"><%const(_NUCBB_CONTENTS)%></label><br />
		<textarea name="nucbbcontents" id="nucbb_contents" cols="40" rows="10" class="formfield" <%disabled%> ><%contents%></textarea><br />
		<label for="nucbb_category"><%const(_NUCBB_CATEGORY)%></label><%categories%><br />
		<%photoupload%>

		<input type="hidden" name="nucbbblogid" value="<%blogid%>" />
		<input type="hidden" name="nucbbid1" value="<%currenttime%>" />
		<input type="hidden" name="nucbbid2" value="<%check%>" />
		<input type="hidden" name="nucbbredirect" value="<%redirecturl%>" />
		<input type="hidden" name="nucbbitemid" value="<%itemid%>" />
		<input type="submit" class="formbutton" value="<%const(_NUCBB_SUBMIT)%>" />
	</div>
</form>
FORMNOTLOGINED;

		$formlogined = <<<FORMLOGINED
<form id="nucbb_inputform" action="<%action%>" enctype="multipart/form-data" method="post">
	<div class="commentform">
		<label for="nucbb_user"><%const(_NUCBB_NAME)%><%username%></label><br />
		<label for="nucbb_subject"><%const(_NUCBB_SUBJECT)%></label>
		<input type="text" name="nucbbsubject" value="<%title%>" id="nucbb_subject" class="formfield" <%disabled%> /><br />
		<label for="nucbb_contents"><%const(_NUCBB_CONTENTS)%></label><br />
		<textarea class="formfield" name="nucbbcontents" cols="40" rows="10"  id="nucbb_contents" <%disabled%> ><%contents%></textarea><br />
		<label for="nucbb_category"><%const(_NUCBB_CATEGORY)%></label><%categories%><br />
		<%photoupload%>

		<input type="hidden" name="nucbbblogid" value="<%blogid%>" />
		<input type="hidden" name="nucbbid1" value="<%currenttime%>" />
		<input type="hidden" name="nucbbid2" value="<%check%>" />
		<input type="hidden" name="nucbbredirect" value="<%redirecturl%>" />
		<input type="hidden" name="nucbbitemid" value="<%itemid%>" />
		<input type="submit" class="formbutton" value="<%const(_NUCBB_SUBMIT)%>" />
	</div>
</form>
FORMLOGINED;

		$commentformnotlogined = <<<COMMNETFORMNOTLOGINED
<form method="post" action="<%action%>" enctype="multipart/form-data" > 
	<div class="commentform"> 
		<label for="nucbb_user"><%const(_NUCBB_NAME)%></label>
		<input name="user" value="<%username%>" size="10" maxlength="60" class="formfield" <%disabled%> /><br />
		<label for="nucbb_mail"><%const(_NUCBB_MAIL)%></label>
		<input name="userid2" value="<%mail%>"size="20" maxlength="60" class="formfield" <%disabled%> /><br />
		<label for="nucbb_url"><%const(_NUCBB_URL)%></label>
		<input name="userid" value="<%url%>"size="20" maxlength="60" class="formfield" <%disabled%> /><br />
		<label for="nucbb_pass"><%const(_NUCBB_PASSWORD)%></label>
		<input type="password" name="nucbbpass" value="" id="nucbb_pass" class="formothers" />
		<input type="checkbox" value="1" name="remember" <%remember%> <%disabled%> /><%const(_NUCBB_REMEMBER)%><br />
		<input type="hidden" name="url" value="<%redirecturl%>" />
		<input type="hidden" name="itemid" value="<%itemid%>" />
		<input type="hidden" name="nucbbid1" value="<%currenttime%>" />
		<input type="hidden" name="nucbbid2" value="<%check%>" />
		<%const(_NUCBB_REPLY)%><br />
		<textarea name="body" cols="43" rows="3" class="formfield" <%disabled%> <%areaid%> ><%contents%></textarea><br />
		<%insertcapcha%>
		<%photoupload%>
		<input type="submit" value="<%const(_NUCBB_SUBMIT)%>" class="formbutton" />
	</div> 
</form>
COMMNETFORMNOTLOGINED;

		$commentformlogined = <<<COMMNETFORMLOGINED
<form method="post" action="<%action%>" enctype="multipart/form-data" > 
	<div class="commentform"> 
		<label for="nucbb_user"><%const(_NUCBB_NAME)%><%username%></label>
		<input type="hidden" name="url" value="<%redirecturl%>" />
		<input type="hidden" name="itemid" value="<%itemid%>" />
		<input type="hidden" name="nucbbid1" value="<%currenttime%>" />
		<input type="hidden" name="nucbbid2" value="<%check%>" />
		<%const(_NUCBB_REPLY)%><br />
		<textarea name="body" cols="43" rows="3" class="formfield" <%disabled%> <%areaid%> ><%contents%></textarea><br />
		<%photoupload%>
		<input type="submit" value="<%const(_NUCBB_SUBMIT)%>" class="formbutton" />
	</div> 
</form>
COMMNETFORMLOGINED;


		$this->createOption('deletetable', _NUCBB_OPTION_UNINSTALL ,'yesno','no');
		$this->createOption('internalpass', _NUCBB_OPTION_INTERNALPASS , 'password', $password);
		$this->createOption('submitformlogined' , _NUCBB_OPTION_FORMLOGIN, 'textarea', $formlogined);
		$this->createOption('submitformnotlogined' , _NUCBB_OPTION_FORMNOTLOGIN, 'textarea', $formnotlogined);
		$this->createOption('commentlogined' , _NUCBB_OPTION_COMMFORMLOGIN, 'textarea', $commentformlogined);
		$this->createOption('commentnotlogined' , _NUCBB_OPTION_COMMFORMNOTLOGIN, 'textarea', $commentformnotlogined);
		$this->createOption('makenewblog', _NUCBB_OPTION_MAKENEWBLOG , 'yesno', 'no');
		$this->createOption('newblogname', _NUCBB_OPTION_NEWBLOGNAME, 'text', '');
		$this->createOption('newblogdesc', _NUCBB_OPTION_NEWBLOGDESC, 'text', '');
		$this->createOption('newshortname', _NUCBB_OPTION_NEWBLOGSHORT, 'text', '');
		$this->createOption('skinname', _NUCBB_OPTION_NEWBLOGSKIN, 'text', 'NucBBskin');
		$this->createBlogOption('recentfirst', _NUCBB_OPTION_RECENTFIRST, 'yesno', 'no');
		$this->createBlogOption('userealname', _NUCBB_OPTION_USEREAL, 'yesno', 'no');
		$this->createBlogOPtion('allowphoto', _NUCBB_OPTION_ALLOWPHOTO, 'yesno', 'no');
		$this->createBlogOption('photomax', _NUCBB_OPTION_PHOTOMAX, 'text', '50');
		$this->createBlogOption('photolinktext', _NUCBB_OPTION_PHOTOLINK, 'text', _NUCBB_PHOTOLINK_DEFAULT);
	}
	
	/**
	  * On de-install, the table gets removed
	  */
	function unInstall() {
		if ($this->getOption('deletetable') == 'yes') {
			sql_query('DROP TABLE ' . sql_table('plugin_nucbb'));
			sql_query('DROP TABLE ' . sql_table('plugin_nucbb_blogs'));
			sql_query('DROP TABLE ' . sql_table('plugin_nucbb_comments'));
		}
	}
	
	function getusername($mem, $bid) {
		if ($this->getBlogOption($bid, 'userealname') == 'yes')
			return $mem->realname;
		else
			return $mem->displayname;
	}
	  
	function doAction($type) {
		global $member, $blog, $manager, $CONF, $DIR_MEDIA;
		switch ($type) {
			case 'submit':
				$blogid = intrequestVar('nucbbblogid');
				if (!$this->blogcheck($blogid)) return _NUCBB_PROHIBITINTHISBLOG;
				$time = requestVar('nucbbid1');
				$check = requestVar('nucbbid2');
				if ((time() - $time > 3600) || ($check != md5($time . $this->getOption('internalpass'))))
					return _NUCBB_INVALIDFORM;
				$contents = requestVar('nucbbcontents');
				$subject = requestVar('nucbbsubject');
				$category = requestVar('nucbbcategory');

				$spamcheck = array (
					'type'  	=> 'NucBB',
					'data'  	=> $subject . ' ' . $contents,
					'return'	=> TRUE,
					'ipblock'   => TRUE
				);

				$params = array ('spamcheck' => & $spamcheck);
				$manager->notify('SpamCheck', $params);
				if (isset($spamcheck['result']) && $spamcheck['result'] == true) {
					return 'Spam Checked';
				}
				
				if ($member->isloggedin()) {
					$authorid = $member->id;
					$username = $this->getusername($member, $blogid);
					$email = $member->email;
					$url = $member->url;
				} else {
					$username = addslashes(requestVar('nucbbuser'));
					$password = md5(requestVar('nucbbpass'));
					$email = addslashes(requestVar('nucbbemail'));
					$url = addslashes(requestVar('nucbburl'));
					$mem = MEMBER::createFromName('NucBBUser');
					$authorid = $mem->id;
					$contents = hsc($contents);
					$remember = intPostVar('nucbbremember');
					if ($remember == 1) {
						$lifetime = time()+2592000;
						setcookie($CONF['CookiePrefix'] . 'comment_user',$username,$lifetime,'/','',0);
						setcookie($CONF['CookiePrefix'] . 'comment_userid', $url,$lifetime,'/','',0);
						setcookie($CONF['CookiePrefix'] . 'comment_userid2', $email,$lifetime,'/','',0);
					}
				}
				if (($subject == '') || ($username == '')) return _NUCBB_NEEDNAMEANDSUBJECT;
				if ($_FILES['userfile']['name']) {
					if (!preg_match('/^image/',$_FILES['userfile']['type']))
						return _NUCBB_ERROR_NOPHOTO;
					$newdir = $DIR_MEDIA . $authorid ;
					if (!file_exists($newdir)) mkdir($newdir, 0777);
					$newname = $newdir . '/' . time() .'_'. $_FILES['userfile']['name'];
					
					if (move_uploaded_file($_FILES['userfile']['tmp_name'], $newname)) {
						$old_level = error_reporting(0);
						list($width, $height, $type, $attr) = @getimagesize($newname);
						error_reporting($old_level);
						$contents .= '<br /><%popup('.basename($newname)."|$width|$height|"
									.$this->getBlogOption($blogid, 'photolinktext') .")%>";
					}
				}
				$blog = new BLOG($blogid);
				$time = $blog->getCorrectTime();
				$timestamp = date('Y-m-d H:i:s',$time);
				$iid = $blog->additem($category, $subject, $contents, '', 
						$blogid, $authorid, $time, 0, 0);
				$insert_query = "INSERT INTO ". sql_table('plugin_nucbb') 
					." SET item_id = $iid,"
					." blog_id = $blogid,"
					." url = '$url',"
					." email = '$email',"
					." username = '$username',"
					." password = '$password',"
					." originaldate = '$timestamp'";
				sql_query($insert_query);
				if (!$password) {
					setcookie('nucbbpass', $password);
				}
				
				header('Location: '.requestVar('nucbbredirect'));
				
				break;
			case 'update':
				$blogid = intrequestVar('nucbbblogid');
				if (!$this->blogcheck($blogid)) return _NUCBB_PROHIBITINTHISBLOG;
				$time = requestVar('nucbbid1');
				$check = requestVar('nucbbid2');
				$itemid = intrequestVar('nucbbitemid');
				if ((time() - $time > 3600) || ($check != md5($time . $itemid. $this->getOption('internalpass'))))
					return _NUCBB_INVALIDFORM;
				$blogid = intrequestVar('nucbbblogid');
				$contents = requestVar('nucbbcontents');
				$subject = requestVar('nucbbsubject');
				$category = requestVar('nucbbcategory');

				$spamcheck = array (
					'type'  	=> 'NucBB',
					'data'  	=> $subject . ' ' . $contents,
					'return'	=> TRUE,
					'ipblock'   => TRUE
				);

				$params = array ('spamcheck' => & $spamcheck);
				$manager->notify('SpamCheck', $params);
				if (isset($spamcheck['result']) && $spamcheck['result'] == true) {
					return _NUCBB_CANNOTUPDATE;
				}

				$manager->loadClass('ITEM');
				if ($member->isloggedin()) {
					$item = ITEM::getItem($itemid,0,0);
					if (($item['authorid'] != $member->id) && !$member->isBlogAdmin($blogid))
						return _NUCBB_CANNOTUPDATE;
					if ($item['authorid'] == $member->id) {
						$username = $this->getusername($member, $blogid);
						$email = $member->email;
						$url = $member->url;
					} else {
						$username = addslashes(requestVar('nucbbuser'));
						$email = addslashes(requestVar('nucbbemail'));
						$url = addslashes(requestVar('nucbburl'));
					}
				} else {
					$password = md5(requestVar('nucbbpass'));
					$query = sql_query("SELECT password FROM ". sql_table('plugin_nucbb') ." WHERE item_id = '$itemid'");
					$this->userinfo = sql_fetch_object($query);
					if (!$password || $password != $this->userinfo->password)
						return _NUCBB_WRONGPASSWORD;
					
					$username = addslashes(requestVar('nucbbuser'));
					$email = addslashes(requestVar('nucbbemail'));
					$url = addslashes(requestVar('nucbburl'));
					$contents = hsc($contents);
				}
				$blog = new BLOG($blogid);
				$time = $blog->getCorrectTime();
				$timestamp = date('Y-m-d H:i:s',$time);
				ITEM::update($itemid, $category, $subject, $contents, '',
						0, 0, 1);
				$insert_query = "UPDATE ". sql_table('plugin_nucbb') 
					." SET url = '$url',"
					." email = '$email',"
					." username = '$username'"
					." where item_id = $itemid";
				sql_query($insert_query);
				if (!$password) {
					setcookie('nucbbpass', $password);
				}
				
				header('Location: '.requestVar('nucbbredirect'));
				
				break;
			case 'delete':
				$blogid = intrequestVar('nucbbblogid');
				if (!$this->blogcheck($blogid)) return _NUCBB_PROHIBITINTHISBLOG;
				$time = requestVar('nucbbid1');
				$check = requestVar('nucbbid2');
				$itemid = intrequestVar('nucbbitemid');
				if ((time() - $time > 3600) || ($check != md5($time . $itemid. $this->getOption('internalpass'))))
					return _NUCBB_INVALIDFORM;
				$blogid = intrequestVar('nucbbblogid');
				$contents = requestVar('nucbbcontents');
				$subject = requestVar('nucbbsubject');
				$category = requestVar('nucbbcategory');
				$manager->loadClass('ITEM');
				if ($member->isloggedin()) {
					$item = ITEM::getItem($itemid,0,0);
					if (($item['authorid'] != $member->id) && !$member->isBlogAdmin($blogid))
						return _NUCBB_CANNOTUPDATE;
				} else {
					$password = md5(requestVar('nucbbpass'));

					$query = sql_query("SELECT password FROM ". sql_table('plugin_nucbb') ." WHERE item_id = '$itemid'");
					$this->userinfo = sql_fetch_object($query);
					sql_free_result($query);
					if (!$password || $password != $this->userinfo->password)
						return _NUCBB_WRONGPASSWORD;
				}
				ITEM::delete($itemid);
				header('Location: '.requestVar('nucbbredirect'));
				break;
			case 'addcomment' :
				$post['itemid'] =	intPostVar('itemid');
				$post['user'] = 	addslashes(postVar('user'));
				$post['userid'] = 	addslashes(postVar('userid'));
				$post['userid2'] = 	addslashes(postVar('userid2'));
				$post['body'] = 	addslashes(postVar('body'));
				$blogid = getBlogIDFromItemID($post['itemid']);
				if (!$this->blogcheck($blogid)) return _NUCBB_PROHIBITINTHISBLOG;
				$time = requestVar('nucbbid1');
				$check = requestVar('nucbbid2');
				if ((time() - $time > 3600) || ($check != md5($time . $post['itemid'].$this->getOption('internalpass'))))
					return _NUCBB_INVALIDFORM;

				$spamcheck = array (
					'type'  	=> 'NucBB',
					'data'  	=> $post['body'] . ' ' . $post['userid'] . ' ' . $post['userid2'],
					'return'	=> TRUE,
					'ipblock'   => TRUE
				);

				$params = array ('spamcheck' => & $spamcheck);
				$manager->notify('SpamCheck', $params);
				if (isset($spamcheck['result']) && $spamcheck['result'] == true) {
					return _NUCBB_CANNOTUPDATE;
				}

				// set cookies when required
				$remember = intPostVar('remember');
				if ($remember == 1) {
					$lifetime = time()+2592000;
					setcookie($CONF['CookiePrefix'] . 'comment_user',$post['user'],$lifetime,'/','',0);
					setcookie($CONF['CookiePrefix'] . 'comment_userid', $post['userid'],$lifetime,'/','',0);
					setcookie($CONF['CookiePrefix'] . 'comment_userid2', $post['userid2'],$lifetime,'/','',0);
				}

				if ($_FILES['userfile']['name']) {
					if (!preg_match('/^image/',$_FILES['userfile']['type']))
						return _NUCBB_ERROR_NOPHOTO;
					if (!is_uploaded_file($_FILES['userfile']['tmp_name']))
						return _NUCBB_ERROR_NOPHOTO;
					$manager->loadClass('ITEM');
					$currentitem = ITEM::getItem($post['itemid'], 0, 0);
					$newdir = $DIR_MEDIA . $currentitem['authorid'];
					if (!file_exists($newdir)) mkdir($newdir, 0777);
					$newname = $newdir . '/' . time() .'_'. addslashes($_FILES['userfile']['name']);
					
					if (move_uploaded_file($_FILES['userfile']['tmp_name'], $newname)) {
						$old_level = error_reporting(0);
						list($width, $height, $type, $attr) = @getimagesize($newname);
						error_reporting($old_level);
						$imagelink = addslashes('<br /><%popup('.basename($newname)."|$width|$height|)%>");
					}
				}

				$comments = new COMMENTS($post['itemid']);

				$blog =& $manager->getBlog($blogid);

				// note: PreAddComment and PostAddComment gets called somewhere inside addComment
				$errormessage = $comments->addComment($blog->getCorrectTime(),$post);

				if (!($errormessage == '1')) {
					return  $errormessage;
				}
				if (!$member->isLoggedIn()) {
					$password = md5(requestVar('nucbbpass'));
					$insert_query = "INSERT INTO ". sql_table('plugin_nucbb_comments') 
						." SET comment_id = ". $this->commentid. ","
						." mail = '".$post['userid2']."', "
						." password = '$password', "
						." imagelink = '$imagelink'";
					sql_query($insert_query);
				} elseif ($imagelink) {
					$insert_query = "INSERT INTO ". sql_table('plugin_nucbb_comments') 
						." SET comment_id = ". $this->commentid. ","
						." imagelink = '$imagelink'";
					sql_query($insert_query);
				}
				header('Location: '.requestVar('url'));
				break;
			case 'commentupdate' :
				$commentid = intrequestVar('itemid'); // use this field as comment id here
				$manager->loadClass('COMMENT');
				$comment = COMMENT::getComment($commentid);
				if (!$this->blogcheck($comment['blogid'])) return _NUCBB_PROHIBITINTHISBLOG;
				if ($member->isloggedin()) {
					if (($comment['memberid'] != $member->id) && !$member->isBlogAdmin($comment['blogid']))
						return _NUCBB_CANNOTUPDATE;
				} else {
					$password = requestVar('nucbbpass');
					$query = sql_query("SELECT password FROM ". sql_table('plugin_nucbb_comments') ." WHERE comment_id = '$commentid'");
					$r = sql_fetch_object($query);
					sql_free_result($query);
					$this->cacheditem = $itemid;
					if (!$password || (md5($password) != $r->password))
						return _NUCBB_WRONGPASSWORD;
				}
				
				$body = postVar('body');

				$spamcheck = array (
					'type'  	=> 'NucBB',
					'data'  	=> $body,
					'return'	=> TRUE,
					'ipblock'   => TRUE
				);

				$params = array ('spamcheck' => & $spamcheck);
				$manager->notify('SpamCheck', $params);
				if (isset($spamcheck['result']) && $spamcheck['result'] == true) {
					return _NUCBB_CANNOTUPDATE;
				}
				
				// intercept words that are too long
				if (preg_match("@[a-zA-Z0-9|\.,;:!\?=/\\]{90,90}@i",$body) != false) 
					return _ERROR_COMMENT_LONGWORD;

				// check length
				if (strlen($body)<3)
					return _ERROR_COMMENT_NOCOMMENT;
				if (strlen($body)>5000)
					return _ERROR_COMMENT_TOOLONG;
				
				
				// prepare body
				$body = COMMENT::prepareBody($body);
				
				// call plugins
				$params = array('body' => &$body);
				$manager->notify('PreUpdateComment',$params);
				
				$query =  'UPDATE '.sql_table('comment'). " SET cbody='" .addslashes($body). "'";
				if (!$member->isloggedin()) {
					$query .= ", cuser='".addslashes(requestvar('user'))."'";
					$query .= ", cmail='".addslashes(requestvar('userid'))."'";
					$query2 = 'UPDATE '.sql_table('plugin_nucbb_comments'). " SET mail='".addslashes(requestvar('userid2'))."' WHERE comment_id=" .$commentid;
					sql_query($query2);
				}
				$query .= " WHERE cnumber=" . $commentid;
				sql_query($query);
//				ACTIONLOG::add(INFO, $query);
				header('Location: '.requestVar('url'));
				
				break;
			case 'commentdelete' :
				$commentid = intrequestVar('itemid'); // use this field as comment id here
				$manager->loadClass('COMMENT');
				$comment = COMMENT::getComment($commentid);
				if (!$this->blogcheck($comment['blogid'])) return _NUCBB_PROHIBITINTHISBLOG;
				if ($member->isloggedin()) {
					if (($comment['memberid'] != $member->id) && !$member->isBlogAdmin($comment['blogid']))
						return _NUCBB_CANNOTUPDATE;
				} else {
					$password = requestVar('nucbbpass');
					$query = sql_query("SELECT password FROM ". sql_table('plugin_nucbb_comments') ." WHERE comment_id = '$commentid'");
					$r = sql_fetch_object($query);
					sql_free_result($query);
					$this->cacheditem = $itemid;
					if (!$password || (md5($password) != $r->password))
						return _NUCBB_WRONGPASSWORD;
				}
				$params = array('commentid' => $commentid);
				$manager->notify('PreDeleteComment', $params);

				// delete the comments associated with the item
				$query = 'DELETE FROM '.sql_table('comment').' WHERE cnumber=' . $commentid;
				sql_query($query);

				$params = array('commentid' => $commentid);
				$manager->notify('PostDeleteComment', $params);

				header('Location: '.requestVar('url'));
				break;
			case 'deledititem' :
				$itemid = intrequestVar('id');
				$manager->loadClass('ITEM');
				$item = ITEM::getItem($itemid, 0, 0);
				$blogid = getBlogIDFromItemID($itemid);
				if (!$this->blogcheck($blogid)) return _NUCBB_PROHIBITINTHISBLOG;
				$GLOBALS['blog'] = & $manager->getBlog($blogid);
				if (!$member->isloggedin()) {
					$password = requestVar('pass');
					$query = sql_query("SELECT url as url,"
									. "email as email,"
									. "username as displayname, "
									. "username as realname, "
									. "originaldate as date,"
									. "password "
									. "FROM ". sql_table('plugin_nucbb') 
									." WHERE item_id = '$itemid'");
					$this->userinfo = sql_fetch_object($query);
					sql_free_result($query);
					$this->cacheditem = $itemid;
					if (!$password || md5($password) != $this->userinfo->password)
						return _NUCBB_WRONGPASSWORD;
					if (requestVar('deledit') == 'del') {
						$head = _NUCBB_CONFIRMDELETEITEM ._NUCBB_PASSWORDREQUEST;
						$form = $this->getBBSTemplate('submitformnotlogined', 'delete', requestVar('redirect'), $itemid);
					} else {
						$head = _NUCBB_EDITITEM . _NUCBB_PASSWORDREQUEST;
						$form = $this->getBBSTemplate('submitformnotlogined', 'edit', requestVar('redirect'), $itemid);
					}
				} else {
					if ($item['authorid'] == $member->getID()) {
						$this->userinfo = $member;
						if (requestVar('deledit') == 'del') {
							$head = _NUCBB_CONFIRMDELETEITEM;
							$form = $this->getBBSTemplate('submitformlogined', 'delete', requestVar('redirect'), $itemid);
						} else {
							$head = _NUCBB_EDITITEM;
							$form = $this->getBBSTemplate('submitformlogined', 'edit', requestVar('redirect'), $itemid);
						}
					} else {
						$query = sql_query("SELECT url as url, "
										. "email as email, "
										. "username as displayname, "
										. "username as realname, "
										. "originaldate as date,"
										. "password "
										. "FROM ". sql_table('plugin_nucbb') 
										." WHERE item_id = '$itemid'");
						$this->userinfo = sql_fetch_object($query);
						sql_free_result($query);
						$this->cacheditem = $itemid;
						if (requestVar('deledit') == 'del') {
							$head = _NUCBB_CONFIRMDELETEITEM ._NUCBB_PASSWORDREQUEST;
							$form = $this->getBBSTemplate('submitformnotlogined', 'delete', requestVar('redirect'), $itemid);
						} else {
							$head = _NUCBB_EDITITEM . _NUCBB_PASSWORDREQUEST;
							$form = $this->getBBSTemplate('submitformnotlogined', 'edit', requestVar('redirect'), $itemid);
						}
					}
				}
				$charset = _CHARSET;
				$admin = $CONF['AdminURL'];
echo <<<EDITITEMFORM
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja-JP" lang="ja-JP">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=$charset" />
	<title>$head</title>
	<link rel="stylesheet" type="text/css" href="$admin/styles/bookmarklet.css" />
</head>
<body>

<h1>$head</h1>
$form
</body>
</html>
EDITITEMFORM;
				return;
			case 'deleditcomment' :
				$commentid = intrequestVar('id');
				$manager->loadClass('COMMENT');
				$comment = COMMENT::getComment($commentid);
				if (!$this->blogcheck($comment['blogid'])) return _NUCBB_PROHIBITINTHISBLOG;
				$blog = new BLOG($item['blogid']);
				if (!$member->isloggedin()) {
					$password = requestVar('pass');
					$query = sql_query("SELECT password FROM ". sql_table('plugin_nucbb_comments') ." WHERE comment_id = '$commentid'");
					$r = sql_fetch_object($query);
					sql_free_result($query);
					if (!$password || (md5($password) != $r->password))
						return _NUCBB_WRONGPASSWORD;
					if (requestVar('deledit') == 'del') {
						$head = _NUCBB_CONFIRMDELETECOMMENT . _NUCBB_PASSWORDREQUEST;
						$form = $this->getBBSTemplate('commentnotlogined', 'delete', requestVar('redirect'), $commentid);
					} else {
						$head = _NUCBB_EDITCOMMENT . _NUCBB_PASSWORDREQUEST;

						$form = $this->getBBSTemplate('commentnotlogined', 'edit', requestVar('redirect'), $commentid);
					}
				} else {
					if ($comment['memberid'] == $member->getID()) {
						$this->userinfo = $member;
						if (requestVar('deledit') == 'del') {
							$head = _NUCBB_CONFIRMDELETECOMMENT;
							$form = $this->getBBSTemplate('commentlogined', 'delete', requestVar('redirect'), $commentid);
						} else {
							$head = _NUCBB_EDITCOMMENT;
							$form = $this->getBBSTemplate('commentlogined', 'edit', requestVar('redirect'), $commentid);
						}
					} else {
						if (requestVar('deledit') == 'del') {
							$head = _NUCBB_CONFIRMDELETECOMMENT . _NUCBB_PASSWORDREQUEST;
							$form = $this->getBBSTemplate('commentnotlogined', 'delete', requestVar('redirect'), $commentid);
						} else {
							$head = _NUCBB_EDITCOMMENT . _NUCBB_PASSWORDREQUEST;
							$form = $this->getBBSTemplate('commentnotlogined', 'edit', requestVar('redirect'), $commentid);
						}
					}
				}
				$charset = _CHARSET;
				$admin = $CONF['AdminURL'];
echo <<<EDITCOMMENTFORM
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja-JP" lang="ja-JP">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=$charset" />
	<title>$head</title>
	<link rel="stylesheet" type="text/css" href="$admin/styles/bookmarklet.css" />
</head>
<body>

<h1>$head</h1>
$form
</body>
</html>
EDITCOMMENTFORM;
				return;

			case 'replycomm' :
				$commentid = intrequestvar('id');
				$manager->loadClass('COMMENT');
				$comment = COMMENT::getComment($commentid);
				if (!$this->blogcheck($comment['blogid'])) return;
				$body = removeBreaks($comment['body']);
				$body = preg_replace('/^.*$/m','> $0',$body);
				header('Content-Type: text/plain; charset='._CHARSET);
				echo $body;
				break;
			case 'replyitem' :
				$itemid = intrequestvar('id');
				$manager->loadClass('ITEM');
				$item = ITEM::getItem($itemid, 0, 0);
				if (!$this->blogcheck($item['blogid'])) return;
				$body = removeBreaks($item['body']);
				$body = preg_replace('/^.*$/m','> $0',$body);
				header('Content-Type: text/plain; charset='._CHARSET);
				echo $body;
				break;
			// other actions result in an error
			default:
				return 'Unexisting action: ' . $type;
		}
		exit;
	}
	
	function event_PostDeleteBlog(&$data)
	{
		$q = "DELETE FROM ".sql_table('plugin_nucbb')." WHERE blogid = ".$data['blogid'];
		sql_query($q);
		$q = "DELETE FROM ".sql_table('plugin_nucbb_blogs')." WHERE blog_id = ".$data['blogid'];
		sql_query($q);
	}

	function event_PostDeleteItem(&$data)
	{
		$q = "DELETE FROM ".sql_table('plugin_nucbb')." WHERE item_id = ".$data['itemid'];
		sql_query($q);
	}

	function event_PostDeleteComment(&$data)
	{
		$q = "DELETE FROM ".sql_table('plugin_nucbb_comments')." WHERE comment_id = ".$data['commentid'];
		sql_query($q);
	}

	function createNewBlog($name, $desc, $short, $skin)
	{
		global $member, $manager, $CONF;
		// Only Super-Admins can do this
		if (!$member->isAdmin()) return FALSE;
		$bname = trim($name);
		$bshortname = trim($short);
		$bid = $CONF['DefaultBlog'];
		$defaultblog = & $manager->getBlog($bid);
		$btimeoffset = $defaultblog->getTimeOffset();
		$bdesc = trim($desc);
		$skin = SKIN::createFromName(trim($skin));
		$bdefskin = $skin->getID();
		if (!isValidShortName($bshortname))
			return FALSE;
		if ($manager->existsBlog($bshortname))
			return FALSE;
		$params = 	array(
					'name' => &$bname,
					'shortname' => &$bshortname,
					'timeoffset' => &$btimeoffset,
					'description' => &$bdescription,
					'defaultskin' => &$bdefskin);

		$manager->notify('PreAddBlog',$params);

		// add slashes for sql queries
		$bname = addslashes($bname);
		$bshortname = addslashes($bshortname);
		$btimeoffset = addslashes($btimeoffset);
		$bdesc = addslashes($bdesc);
		$bdefskin = addslashes($bdefskin);
		$b =& $manager->getBlog($CONF['DefaultBlog']);
		$burl = $b->getURL().$bshortname.".php";

		// create blog
		$query = 'INSERT INTO '.sql_table('blog')." (bname, bshortname, bdesc, btimeoffset, bdefskin, burl) VALUES ('$bname', '$bshortname', '$bdesc', '$btimeoffset', '$bdefskin', '$burl')";
		sql_query($query);
		$blogid    = sql_insert_id();
		$blog    =& $manager->getBlog($blogid);
		
		// register database
		sql_query('INSERT INTO '.sql_table('plugin_nucbb_blogs'). "(blog_id) VALUES ($blogid)");
		
		// create new category
		sql_query('INSERT INTO '.sql_table('category')." (cblog, cname, cdesc) VALUES ($blogid, 'General','Items that do not fit in other categories')");
		$catid = sql_insert_id();
		
		// set as default category
		$blog->setDefaultCategory($catid);
		$blog->setAllowPastPosting(0);
		$blog->writeSettings();
		
		// create team member
		$memberid = $member->getID();
		$query = 'INSERT INTO '.sql_table('team')." (tmember, tblog, tadmin) VALUES ($memberid, $blogid, 1)";
		sql_query($query);
		
		//make NucBBUser team member
		$nucbbuser = MEMBER::createFromName('NucBBUser');
		$memberid = $nucbbuser->getID();
		$query = 'INSERT INTO '.sql_table('team')." (tmember, tblog, tadmin) VALUES ($memberid, $blogid, 0)";
		sql_query($query);

		$params = array('blog' => &$blog);
		$manager->notify('PostAddBlog',$params);
		$params = array('catid' => $catid);
		$manager->notify('PostAddCategory',$params);
		return TRUE;
	}

	function event_PostPluginOptionsUpdate(&$data)
	{
		if ($data['plugid'] == $this->getID() && $data['context'] == 'global') {
			if ($this->getOption('makenewblog') == 'yes') {
				$result = $this->createNewBlog(
					$this->getOption('newblogname'),
					$this->getOption('newblogdesc'),
					$this->getOption('newshortname'),
					$this->getOption('skinname')
					);
				$this->setOption('makenewblog', 'no');
				$this->setOption('newblogname', '');
				$this->setOption('newblogdesc', '');
				$this->setOption('newshortname', '');
				if (!$result) echo _NUCBB_CANNOTMAKEBB;
			}
		}
	}
	
	function updateTime($itemid, $time)
	{
		$timestamp = date('Y-m-d H:i:s',$time);
		$query = 'UPDATE '.sql_table('item')." SET itime='$timestamp' WHERE inumber=$itemid";
		sql_query($query);
	}
	
	function blogcheck($bid)
	{
		if ($bid == $this->currentblog) return 1;
		$query = "SELECT * FROM ".sql_table('plugin_nucbb_blogs')." WHERE blog_id = ".$bid;
		$result = sql_query($query);
		$rows = sql_num_rows($result);
		sql_free_result($result);
		if ($rows) {
			$this->currentblog = $bid;
			return 1;
		} else
			return 0;
	}
	
	
	function event_PostAddComment(&$data)
	{
		$this->commentid = $data['commentid'];
		$itemid = $data['comment']['itemid'];
		$blogid = getBlogIDFromItemID($itemid);
		if ($this->getBlogOption($blogid, 'recentfirst') == 'yes') {
			$this->updateTime($itemid, $data['comment']['timestamp']);
		}
	}

	function replacePopupCode($matches) {
		global $CONF, $manager, $itemid;
		$manager->loadClass('ITEM');
		$item = ITEM::getItem($itemid, 0, 0);
		$filename = $matches[1];
		if (!strstr($filename,'/')) {
			$manager->loadClass('ITEM');
			$item = ITEM::getItem($itemid, 0, 0);
			$filename = $item['authorid'] . '/' . $filename;
		}
		
		$width = $matches[2];
		$height = $matches[3];
		$popup = "window.open(this.href,'imagepopup','status=no,toolbar=no,scrollbars=no,resizable=yes,width=$width,height=$height');return false;";
		$text = $this->getBlogOption($item['blogid'], 'photolinktext');
		$rawlink = $CONF['Self'] . "?imagepopup=" . hsc($filename) . "&amp;width=$width&amp;height=$height&amp;imagetext=" . urlencode(hsc($text));
		$link = '<a href="' . $rawlink. '" onclick="'. $popup.'" >' . hsc($text) . '</a>';

//		$link = '<a href="' . $filename. '" onclick="'. $popup.'" >' . $text . '</a>';
		return $link;
	}

	function event_PreComment($data) {
		global $manager, $itemid;
		if ($this->getBlogOption($data['comment']['blogid'], 'allowphoto') == 'yes') {
			$res = sql_query('SELECT imagelink FROM ' . sql_table('plugin_nucbb_comments')
							. ' WHERE comment_id = '. $data['comment']['commentid']);
			$com = sql_fetch_array($res);
			$link = stripslashes($com['imagelink']);
			sql_free_result($res);
			if ($link) {
				$comment =  &$data["comment"];
				if ($manager->pluginInstalled('NP_Thumbnail')) {
					$plugthumb = $manager->getPlugin('NP_Thumbnail');
					$manager->loadClass('ITEM');
					$item = ITEM::getItem($itemid, 0, 0);
					$plugthumb->curItem = (object) $item;
					if (method_exists($plugthumb, 'replacePopupCode'))
						$comment['body'] .= preg_replace_callback("#<\%popup\((.*?)\|(.*?)\|(.*?)\|(.*?)\)%\>#", array(&$plugthumb, 'replacePopupCode'), $link);
					else
						$comment['body'] .= preg_replace_callback("#<\%popup\((.*?)\|(.*?)\|(.*?)\|(.*?)\)%\>#", array(&$plugthumb, 'replacecallback'), $link);
				} else {
					$comment['body'] .= preg_replace_callback("#<\%popup\((.*?)\|(.*?)\|(.*?)\|(.*?)\)%\>#", array(&$this, 'replacePopupCode'), $link);
				}
			}
		}
	}

	function doSkinVar($skinType, $param = '') {
		global $member, $blog, $CONF, $itemid, $catid;
		$currenturl = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
		switch ($param) {
		case '' :
		case 'form' :
			if ($member->isloggedin()) {
				echo $this->getBBSTemplate('submitformlogined', 'add', $currenturl, $catid);
			} else {
				echo $this->getBBSTemplate('submitformnotlogined', 'add', $currenturl, $catid);
			}
			break;
		case 'commentform' :
			if ($skinType == 'item') {
				if ($member->isloggedin()) {
					echo $this->getBBSTemplate('commentlogined', 'add', $currenturl, $itemid);
				} else {
					$temp = $this->getBBSTemplate('commentnotlogined', 'add', $currenturl, $itemid);
					$actions = new ACTIONS('item');
					$parser = new PARSER(array('callback'), $actions);
					$actions->setParser($parser);
					$parser->parse($temp);
				}
			}
			break;
		case 'javascript' :
			echo '<script type="text/javascript" src="'.$CONF['PluginURL'].'/nucbb/nucbbscript.js"></script>';
			break;
		case 'version' :
			echo $this->getName().$this->getVersion();
			break;
		}
	}

	function doTemplateVar(&$item, $param1, $param2 = '', $param3 = '') {
		global $blog, $currentTemplateName, $manager, $member, $CONF, $itemid, $catid;
		$currenturl = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
		if ($item->itemid != $this->cacheditem) {
			$iid = $item->itemid;
			$query = sql_query("SELECT url as url, "
							. "email as email, "
							. "username as displayname, "
							. "username as realname, "
							. "originaldate as date "
							. "FROM ". sql_table('plugin_nucbb') 
							. " WHERE item_id = '$iid'" );
			$this->userinfo = sql_fetch_object($query);
			sql_free_result($query);
			if (!$this->userinfo) {
				$this->userinfo = MEMBER::createFromID($item->authorid);
				$this->userinfo->date = $item->timestamp;
			}
			$this->cacheditem = $iid;
		}
		switch($param1) {
			case 'name' :
				echo $this->getusername($this->userinfo, $item->blogid);
				break;
			case 'date' :
				echo strftime($param2,strtotime($this->userinfo->date));
				break;
			case 'mail' :
				echo $this->userinfo->email;
				break;
			case 'url' :
				echo $this->userinfo->url;
				break;
			case 'avatar' :
				$default = urlencode($CONF['PluginURL'].'/nucbb/noavatar.gif');
				$output = '<img src="http://www.gravatar.com/avatar.php?';
				$output .= '&gravatar_id='.md5($this->userinfo->email);
				$output .= '&rating=R&size=32&default='.$default.'"';
				$output .= ' width="32" height="32" alt="'.$this->getusername($this->userinfo, $item->blogid).'" />';
				echo $output;
				break;
			case 'comments' :
				$itemid = $item->itemid;
				$itemactions = new ITEMACTIONS($blog);
				$itemactions->setShowComments(1);
				if (method_exists($manager, 'getTemplate'))
					$template =& $manager->getTemplate($currentTemplateName);
				else
					$template = TEMPLATE::read($currentTemplateName);
				$parser =& new PARSER($itemactions->getDefinedActions(),$itemactions);
				$itemactions->setParser($parser);
				$comments = new COMMENTS($item->itemid);
				$comments->setItemActions($itemactions);
				$comments->showComments($template);
				break;
			case 'form' :
				if ($member->isloggedin()) {
					echo $this->getBBSTemplate('commentlogined', 'add', $currenturl, $item->itemid);
				} else {
					$temp = $this->getBBSTemplate('commentnotlogined', 'add', $currenturl, $item->itemid);
					$actions = new ACTIONS('item');
					$parser = new PARSER(array('callback'), $actions);
					$actions->setParser($parser);
					$parser->parse($temp);
				}
				break;
			case 'deleditlink' :
				if ($member->isloggedin()) {
					if (($item->authorid == $member->id) || $member->isBlogAdmin($blog->getID())) {
						echo '<form style="display:inline" method="post" action="' .$CONF['ActionURL'].'?action=plugin&name=NucBB&type=deledititem'.'">';
						// in case of item page do not delete
						if (!$itemid)
							echo '<label><input type="radio" name="deledit" value="del">' . _NUCBB_DELETE . '</label>';
						echo '<label><input type="radio" name="deledit" value="edit">' . _NUCBB_EDIT . '</label>';
						// in case of category page set category id
						if ($catid)
							echo '<input type="hidden" name="catid" value="'.$catid.'" />';
						echo '<input type="hidden" name="bid" value="'.$blog->getID().'" />';
						echo '<input type="hidden" name="id" value="'.$item->itemid.'" />';
						echo '<input type="hidden" name="redirect" value="'.$currenturl.'" />';
						echo '<input type="submit" value="' . _NUCBB_SUBMIT . '">';
						echo '</form>';
					}
				} else {
					$mem = MEMBER::createFromName('NucBBUser');
					if ($item->authorid == $mem->id) {

						echo '<form style="display:inline" method="post" action="' .$CONF['ActionURL'].'?action=plugin&name=NucBB&type=deledititem'.'">';
						echo 'Pass: <input type="password" size="5" value="" name="pass" />';
						// in case of item page do not delete
						if (!$itemid)
							echo '<label><input type="radio" name="deledit" value="del">' . _NUCBB_DELETE . '</label>';
						echo '<label><input type="radio" name="deledit" value="edit">' . _NUCBB_EDIT . '</label>';
						// in case of category page set category id
						if ($catid)
							echo '<input type="hidden" name="catid" value="'.$catid.'" />';
						echo '<input type="hidden" name="bid" value="'.$blog->getID().'" />';
						echo '<input type="hidden" name="id" value="'.$item->itemid.'" />';
						echo '<input type="hidden" name="redirect" value="'.$currenturl.'" />';
						echo '<input type="submit" value="' . _NUCBB_SUBMIT . '">';
						echo '</form>';
					}
				}
				break;
			case 'reply' :
				$iid = $item->itemid;
				$url = $CONF['ActionURL'].'?action=plugin&name=NucBB&type=replyitem&id='.$iid;
				echo '<input type="button" value="' . _NUCBB_REPLY . '" onclick="np_nucbb_sendreply(';
				echo "$iid , '$url');return true;\" />";
				break;
		}
	}
	
	function doTemplateCommentsVar(&$item, &$comment, $param1) {
		global $member, $CONF, $blog, $manager;
		$currenturl = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
		switch($param1) {
			case 'name' :
				if ($comment['memberid']) {
					$m = MEMBER::createFromId($comment['memberid']);
					echo $this->getusername($m, $item->blogid);
				} else {
					echo $comment['user'];
				}
				break;
			case 'mail' :
				if ($comment['memberid']) {
					$m = MEMBER::createFromId($comment['memberid']);
					echo $m->getEmail();
				} else {
					$query = "SELECT mail FROM ". sql_table('plugin_nucbb_comments') ." WHERE comment_id = ".$comment['commentid'];
					
					$result = sql_query($query);
					$r = sql_fetch_assoc($result);
					sql_free_result($result);
					echo $r['mail'];
				}
				break;
			case 'avatar' :
				$default = urlencode($CONF['PluginURL'].'/nucbb/noavatar.gif');
				if ($comment['memberid']) {
					$m = MEMBER::createFromId($comment['memberid']);
					$mail = $m->getEmail();
				} else {
					$query = "SELECT mail FROM ". sql_table('plugin_nucbb_comments') ." WHERE comment_id = ".$comment['commentid'];
					
					$result = sql_query($query);
					$r = sql_fetch_assoc($result);
					sql_free_result($result);
					$mail = $r['mail'];
				}
				if (strpos($mail, '@')) {
					$output = '<img src="http://www.gravatar.com/avatar.php?';
					$output .= '&gravatar_id='.md5($mail);
					$output .= '&rating=R&size=32&default='.$default.'"';
					$output .= ' width="32" height="32" alt="'.$comment['user'].'" />';
					echo $output;
				}
				break;
			case 'deleditlink' :
				if ($member->isloggedin()) {
					if (($comment['memberid'] == $member->id) || $member->isBlogAdmin($blog->getID())) {
						echo '<form style="display:inline" method="post" action="' .$CONF['ActionURL'].'?action=plugin&name=NucBB&type=deleditcomment'.'">';
						echo '<label><input type="radio" name="deledit" value="del">' . _NUCBB_DELETE . '</label>';
						echo '<label><input type="radio" name="deledit" value="edit">' . _NUCBB_EDIT . '</label>';
						echo '<input type="hidden" name="id" value="'.$comment['commentid'].'" />';
						echo '<input type="hidden" name="redirect" value="'.$currenturl.'" />';
						echo '<input type="submit" value="' . _NUCBB_SUBMIT . '">';
						echo '</form>';
					}
				} else {
					if (!$comment['memberid']) {
						echo '<form style="display:inline" method="post" action="' .$CONF['ActionURL'].'?action=plugin&name=NucBB&type=deleditcomment'.'">';
						echo 'Pass: <input type="password" size="5" value="" name="pass" />';
						echo '<label><input type="radio" name="deledit" value="del">' . _NUCBB_DELETE . '</label>';
						echo '<label><input type="radio" name="deledit" value="edit">' . _NUCBB_EDIT . '</label>';
						echo '<input type="hidden" name="id" value="'.$comment['commentid'].'" />';
						echo '<input type="hidden" name="redirect" value="'.$currenturl.'" />';
						echo '<input type="submit" value="' . _NUCBB_SUBMIT . '">';
						echo '</form>';
					}
				}
				break;
			case 'reply' :
				$res = sql_query('SELECT citem FROM '.sql_table('comment').' WHERE cnumber=' . $comment['commentid']);
				$r = sql_fetch_object($res);
				$itemid = $r->citem;
				sql_free_result($res);
//				$itemid = $item['itemid'];
				$url = $CONF['ActionURL'].'?action=plugin&name=NucBB&type=replycomm&id='.$comment['commentid'];
				echo '<input type="button" value="' . _NUCBB_REPLY . '" onclick="np_nucbb_sendreply(';
				echo "$itemid , '$url');return true;\" />";
				break;
			case 'title' :
				$res = sql_query('SELECT citem FROM '.sql_table('comment').' WHERE cnumber=' . $comment['commentid']);
				$r = sql_fetch_object($res);
				$itemid = $r->citem;
				sql_free_result($res);
				$manager->loadClass('ITEM');
				$item = ITEM::getItem($itemid,0,0);
				echo 'Re: '.$item['title'];
				break;
			case 'abbr' :
				echo shorten($comment['body'],20,'...');
				break;
		}
	}
	

	// $type = form type, $mode = add/edit, $id = itemid(comment form)/commentid(comment editing)/catid(category skin)
	function getBBSTemplate($type, $mode, $url, $id="") {
		global $blog, $member, $manager, $CONF;
		$contents = $this->getOption($type);

		// replace constants
		$contents = preg_replace_callback( 
				'#<%const\((.+?)\)%>#s', 
				create_function(
					'$matches',
					'return constant($matches[1]);'
				),
				$contents
			); 


		$replace_array = array ();
		switch ($type) {
		case 'submitformlogined' :
		case 'submitformnotlogined' :
/********************************
//<%action%>
//<%itemid%>
//<%contents%>
//<%redirecturl%>
//<%username%>
//<%remember%>
//<%mail%>
//<%url%>
//<%categories%>
//<%blogid%>
//<%currenttime%>
//<%check%>
//<%title%>
//<%disabled%>
//<%photoupload%>
********************************/

			if ($mode=='add') {
				$replace_array['<%action%>'] = $CONF['ActionURL'] .
					'?action=plugin&name=NucBB&type=submit';
				$replace_array['<%itemid%>'] = '';
				$replace_array['<%contents%>'] = "";
				$replace_array['<%title%>'] = '';
				$replace_array['<%disabled%>'] = '';
				$defcatid = ($id) ? $id : $blog->getDefaultCategory();
				$id = ''; // for form checking
			} else {
				if ($mode == 'edit') {
					$replace_array['<%action%>'] = $CONF['ActionURL'] .
						'?action=plugin&name=NucBB&type=update';
					$replace_array['<%disabled%>'] = '';
				} else {
					$replace_array['<%action%>'] = $CONF['ActionURL'] .
						'?action=plugin&name=NucBB&type=delete';
					$replace_array['<%disabled%>'] = 'disabled="disabled"';
				}
				$replace_array['<%itemid%>'] = "$id";
				$item =& $manager->getItem($id,1,1);
				if ($blog->convertBreaks()) {
					$item['body'] = removeBreaks($item['body']);
				}
				$replace_array['<%contents%>'] = $item['body'];
				$replace_array['<%title%>'] = $item['title'];
				$defcatid = $item['catid'];
			}
			$replace_array['<%redirecturl%>'] = $url;


			$bid = ($blog) ? $blog->getID() : requestVar('bid');
			if ($member->isLoggedIn()) {
				if ($mode == 'add') {
					$replace_array['<%username%>'] = $this->getusername($member, $bid);
				} else {
					$replace_array['<%username%>'] = $this->getusername($this->userinfo, $bid);
					$replace_array['<%mail%>'] = $this->userinfo->email;
					$replace_array['<%url%>'] = $this->userinfo->url;
					$replace_array['<%remember%>'] = '';
				}
			} else {
				if(cookieVar('comment_user')){
					$replace_array['<%username%>'] = hsc(cookieVar('comment_user'));
					$replace_array['<%remember%>'] = 'checked="checked"';
				} else {
					if ($mode == 'add') {
						$replace_array['<%username%>'] = '';
						$replace_array['<%remember%>'] = '';
					} else {
						$replace_array['<%username%>'] = $this->getusername($this->userinfo, $bid);
						$replace_array['<%remember%>'] = '';
					}
				}
				if (cookieVar('comment_userid')) {
					$replace_array['<%mail%>'] = hsc(cookieVar('comment_userid2'));
					$replace_array['<%url%>'] = hsc(cookieVar('comment_userid'));
				} else {
					if ($mode == 'add') {
						$replace_array['<%mail%>'] = '';
						$replace_array['<%url%>'] = '';
					} else {
						$replace_array['<%mail%>'] = $this->userinfo->email;
						$replace_array['<%url%>'] = $this->userinfo->url;
					}
				}

			}
			$query = 'SELECT catid as catid, cname as catname FROM '.sql_table('category').' WHERE cblog=' . $bid . ' ORDER BY cname ASC';
			$res = sql_query($query);
			$menu = '<select name="nucbbcategory" id="nucbb_category" class="formbutton" >';
			if ($this->getBlogOption($bid, 'allowphoto') == 'yes') {
				$maxsize = $this->getBlogOption($bid, 'photomax');
				$replace_array['<%photoupload%>'] = 
					'<input type="hidden" name="MAX_FILE_SIZE" value="'. $maxsize * 1024 .'">'."\n".
					'<label for="nucbb_upload">'. _NUCBB_PHOTOFIRST . $maxsize . _NUCBB_PHOTOLAST . '</label>'.
					'<input name="userfile" type="file" /><br />';
			} else {
				$replace_array['<%photoupload%>'] = '';
			}

			while ($data = sql_fetch_assoc($res)) {
				$menu .= '<option value="' . $data['catid'] . '"';
				if ($defcatid == $data['catid'])
					$menu .= ' selected="selected">';
				else
					$menu .= '>';
				$menu .= $data['catname'] . "</option>\n";
			}
			sql_free_result($res);
			$menu .= '</select>';
			$replace_array['<%categories%>'] = $menu;
			$replace_array['<%blogid%>'] = $bid;
			$time = time();
			$replace_array['<%currenttime%>'] = $time;
			$check = $time . $id . $this->getOption('internalpass');
			$replace_array['<%check%>'] = md5($check);
			
			return strtr($contents, $replace_array);
		case 'commentlogined' :
		case 'commentnotlogined' :
/*****************************************
//<%action%>
//<%contents%>
//<%username%>
//<%mail%>
//<%url%>
//<%remember%>
//<%redirecturl%>
//<%itemid%>
//<%currenttime%>
//<%check%>
//<%disabled%>
//<%areaid%>
//<%photoupload%>
******************************************/
			if ($mode == 'add') {
				$replace_array['<%areaid%>'] = 'id="nucbb_commarea_'.$id.'"';
				$replace_array['<%action%>'] = $CONF['ActionURL'] .
						'?action=plugin&name=NucBB&type=addcomment';
				$replace_array['<%contents%>'] = "";
				$replace_array['<%disabled%>'] = '';
				if (getNucleusVersion() >= 320)
					$replace_array['<%insertcapcha%>'] = '<%callback(FormExtra,commentform-notloggedin)%><br />';
				else
					$replace_array['<%insertcapcha%>'] = '';
				if ($member->isLoggedIn()) {
					$commentobj = COMMENT::getComment($id);
					$replace_array['<%username%>'] = $this->getusername($member, $commentobj['blogid']);
				} else {
					if (cookieVar('comment_user')) {
						$replace_array['<%username%>'] = hsc(cookieVar('comment_user'));
						$replace_array['<%remember%>'] = 'checked="checked"';
					}else{
						$replace_array['<%username%>'] = '';
						$replace_array['<%remember%>'] = '';
					}
					$replace_array['<%mail%>'] = hsc(cookieVar('comment_userid2'));
					$replace_array['<%url%>'] = hsc(cookieVar('comment_userid'));
				}
			} else {
				if ($mode == 'edit') {
					$replace_array['<%action%>'] = $CONF['ActionURL'] .
						'?action=plugin&name=NucBB&type=commentupdate';
					$replace_array['<%disabled%>'] = '';
				} else {
					$replace_array['<%action%>'] = $CONF['ActionURL'] .
						'?action=plugin&name=NucBB&type=commentdelete';
					$replace_array['<%disabled%>'] = 'disabled="disabled"';
				}
				if (cookieVar('comment_user'))
					$replace_array['<%remember%>'] = 'checked="checked"';
				else
					$replace_array['<%remember%>'] = '';
				$replace_array['<%areaid%>'] = '';
				$replace_array['<%insertcapcha%>'] = '';
				$comment = COMMENT::getComment($id);
				$replace_array['<%username%>'] = $comment['user'];
				$replace_array['<%url%>'] = $comment['userid'];
				$replace_array['<%contents%>'] = removeBreaks($comment['body']);
				$query = "SELECT mail FROM ". sql_table('plugin_nucbb_comments') ." WHERE comment_id = ".$id;
				$result = sql_query($query);
				$r = sql_fetch_assoc($result);
				sql_free_result($result);
				$replace_array['<%mail%>'] = $r['mail'];
			}
			$replace_array['<%redirecturl%>'] = $url;
			// it works as comment id in update mode
			$replace_array['<%itemid%>'] = "$id";
			$time = time();
			$replace_array['<%currenttime%>'] = $time;
			$check = $time . $id . $this->getOption('internalpass');
			$replace_array['<%check%>'] = md5($check);

			$manager->loadClass('ITEM');
			$item = ITEM::getItem($id,0,0);
			$bid = $item['blogid'];
			if ($this->getBlogOption($bid, 'allowphoto') == 'yes') {
				$maxsize = $this->getBlogOption($bid, 'photomax');
				$replace_array['<%photoupload%>'] = 
					'<input type="hidden" name="MAX_FILE_SIZE" value="'. $maxsize * 1024 .'">'."\n".
					'<label for="nucbb_upload">'. _NUCBB_PHOTOFIRST . $maxsize . _NUCBB_PHOTOLAST . '</label>'.
					'<input name="userfile" type="file" /><br />';
			} else {
				$replace_array['<%photoupload%>'] = '';
			}

			return strtr($contents, $replace_array);
		}
	}

}
?>