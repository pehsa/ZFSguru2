/* Tab: Shares */

function sambaNewShare() {
	// document.getElementById('sambanewshare-button').className = 'hidden';
	document.getElementById('sambanewshare').className = 'normal';
	document.getElementById('newshare_sharename').focus();
}

function sambaNewShareFilesystem(o) {
	if (o.value === '/mp/') {
		document.getElementById('newshare_sharename').value = '';
		document.getElementById('newshare_mountpoint').className = 'normal';
		document.getElementById('newshare_mp').focus();
		document.getElementById('newshare_mp').value = '/';
	} else {
		document.getElementById('newshare_mountpoint').className = 'hidden';
		const slashOffset = o.value.lastIndexOf('/');
		document.getElementById('newshare_sharename').value = o.value.substr(slashOffset + 1, 8);
	}
}

function sambaNewShareProfile(o) {
	document.getElementById('newshare_span_public').className = 'hidden';
	document.getElementById('newshare_span_protected').className = 'hidden';
	document.getElementById('newshare_span_private').className = 'hidden';
	document.getElementById('newshare_span_noaccess').className = 'hidden';

	switch (o.value) {
		case 'public':
			document.getElementById('newshare_span_public').className = 'normal';
			break;
		case 'protected':
			document.getElementById('newshare_span_protected').className = 'normal';
			break;
		case 'private':
			document.getElementById('newshare_span_private').className = 'normal';
			document.getElementById('newshare_private').className = 'normal';
			break;
		default:
			document.getElementById('newshare_span_noaccess').className = 'normal';
			break;
	}
}

function sambaShareUserList() {
	sambaShareGroupListsHide();
	document.getElementById('samba_share_usertab').className =
		'samba_share_usergrouptab samba_share_usergrouptab_active';
	document.getElementById('samba_share_grouptab').className =
		'samba_share_usergrouptab';
	document.getElementById('samba_share_userlist').style.display = 'block';
	return false;
}

function sambaShareGroupList() {
	sambaShareGroupListsHide();
	document.getElementById('samba_share_usertab').className =
		'samba_share_usergrouptab';
	document.getElementById('samba_share_grouptab').className =
		'samba_share_usergrouptab samba_share_usergrouptab_active';
	document.getElementById('samba_share_grouplist').style.display = 'block';
	return false;
}

function sambaShareGroupListsHide() {
	const containerChildren = document.getElementById('samba_share_userlist_container').getElementsByTagName('div');
	for (let i = 0; i < containerChildren.length; i++) {
		containerChildren[i].style.display = 'none';
		if (containerChildren[i].id.substr(3).length > 0) {
			// TODO [???]
			//   document.getElementById(extraTabChildren[i].id.substr(3)).setAttribute('class', 'hidden');
		}
	}
}

function sambaShareGroupSelect(o) {
	const groupName = o.id.substr(13);
	// 'everyone' group
	if (groupName === 'share') {
		return false;
	}
	// check for existence of specific group list
	const groupListName = 'samba_share_grouplist_' + groupName;
	if (document.getElementById(groupListName).id.length > 0) {
		// hide user and group lists
		document.getElementById('samba_share_userlist').style.display = 'none';
		document.getElementById('samba_share_grouplist').style.display = 'none';
		// display specific group list
		document.getElementById(groupListName).style.display = 'block';
	}
	return false;
}

function sambaManualShares(o) {
	const iframe = document.getElementById('samba_shares_manualiframe');
	iframe.className = 'normal';
	const suffixUpperCase = (o.value + '').toUpperCase();
	let suffix = '';
	for (let i = 0; i < suffixUpperCase.length; i++) {
		if (suffixUpperCase.substr(i, 1) !== ' ') {
			suffix += suffixUpperCase.substr(i, 1);
		}
	}
	if (iframe.className !== 'normal') {
		iframe.className = 'normal';
	}
	iframe.src = 'http://www.samba.org/samba/docs/man/manpages-3/smb.conf.5.html#' + suffix;
}
