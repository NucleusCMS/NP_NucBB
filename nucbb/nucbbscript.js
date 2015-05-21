
	function np_nucbb_sendreply(itemid, url){
		var xmlhttp=false;
		try {
			xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try {
				xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (E) {
				xmlhttp = false;
			}
		}
		if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
			xmlhttp = new XMLHttpRequest();
		}
		areaid = 'nucbb_commarea_' + itemid;
		xmlhttp.open("GET", url,true);
		xmlhttp.onreadystatechange=function() {
			if (xmlhttp.readyState==4) {
				document.getElementById(areaid).value = xmlhttp.responseText
			}
		}
		xmlhttp.send(null)
	}

