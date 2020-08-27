/* NFS share button */
function nfsNewShare() {
	document.getElementById('nfsnewshare').className = 'normal';
	return false;
}

/* NFS share profile */
function nfsNewShareProfile(o) {
	document.getElementById('newshare_span_public').className = 'hidden';
	document.getElementById('newshare_span_protected').className = 'hidden';
	document.getElementById('newshare_span_private').className = 'hidden';
	if (o.value === 'public') {
		document.getElementById('newshare_span_public').className = 'normal';
	} else if (o.value === 'protected') {
		document.getElementById('newshare_span_protected').className = 'normal';
	} else if (o.value === 'private') {
		document.getElementById('newshare_span_private').className = 'normal';
		document.getElementById('newshare_private').className = 'normal';
	}
}

function nfsMassAction(o) {
	if (o.value === 'private')
		document.getElementById('nfs_ma_pi').className = 'normal';
	else
		document.getElementById('nfs_ma_pi').className = 'hidden';
}

function nfsModifyShareProfile(o) {
	document.getElementById('modshare_span_public').className = 'hidden';
	document.getElementById('modshare_span_protected').className = 'hidden';
	document.getElementById('modshare_span_private').className = 'hidden';
	if (o.value === 'public') {
		document.getElementById('modshare_span_public').className = 'normal';
	} else if (o.value === 'protected') {
		document.getElementById('modshare_span_protected').className = 'normal';
	} else if (o.value === 'private') {
		document.getElementById('modshare_span_private').className = 'normal';
		document.getElementById('modshare_private').className = 'normal';
	}
}
