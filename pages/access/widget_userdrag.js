/* drag & drop for Shares and Users tab */

function drag(e) {
	// drag data
	target = e.target;
	action = e.target.id.substr(0, 12);
	if (action == 'sambaimagexx') {
		target = e.target.parentNode;
		action = target.id.substr(0, 12);
	}
	dragUser = target.id.substr(13);
	dragSource = target.parentNode.id;
	e.dataTransfer.setData('source', dragSource);

	// page: Samba shares
	if (action == 'standarduser') {
		e.dataTransfer.setData('type', 'standarduser');
		e.dataTransfer.setData('name', dragUser);
	} else if (action == 'useringroupx') {
		e.dataTransfer.setData('type', 'useringroup');
		e.dataTransfer.setData('name', dragUser);
	} else if (action == 'sambagroupxx') {
		e.dataTransfer.setData('type', 'sambagroup');
		e.dataTransfer.setData('name', dragUser);
	} else if (action == 'sambaguestxx') {
		e.dataTransfer.setData('type', 'sambaguest');
		e.dataTransfer.setData('name', dragUser);
	}
	// page: Samba users
	else if (action == 'sambauserxxx') {
		// save user and group of dragged object
		e.dataTransfer.setData('user', dragUser);
		e.dataTransfer.setData('group', target.parentNode.id.substr(13));
	} else if (action == 'sambaimagexx') {
		alert('UNUSED');
		// save user and group of dragged object
		e.dataTransfer.setData('user', dragUser);
		e.dataTransfer.setData('group', target.parentNode.parentNode.id.substr(13));
	};
}

function allowDrop_shares(e)
// TODO: make sambaguest deny drop for its own access list group
{
	var dragType = e.dataTransfer.getData('type');
	var dragSource = e.dataTransfer.getData('source').substr(11);
	var dragTarget = e.target.parentNode.id.substr(18);
	if (dragType == 'standarduser' || dragType == 'sambagroup' ||
		dragType == 'sambaguest' || dragType == 'useringroup') {
		if (dragSource != dragTarget) {
			e.preventDefault();
		}
	}
}

function allowDrop_grouplist(e) {
	var dragType = e.dataTransfer.getData('type');
	var dragSource = e.dataTransfer.getData('source');
	if (dragType == 'standarduser' || dragType == 'sambagroup') {
		if (dragSource.substr(0, 11) == 'sambagroup_') {
			e.preventDefault();
		}
	}
}

function allowDrop_users(e) {
	var oldGroup = e.dataTransfer.getData('group');
	if (!oldGroup) {
		return false;
	};
	var newGroup = e.target.parentNode.id.substr(17);
	var dragObject = e.target.parentNode.id.substr(0, 17);
	if (dragObject == 'samba_users_drop_' || dragObject == 'samba_users_drag_') {
		if (newGroup != oldGroup) {
			e.preventDefault();
		};
	};
}

function drop_shares(e)
// TODO: many
{
	// prevent default action
	e.preventDefault();

	// fetch drag information
	var dragName = e.dataTransfer.getData('name');

	// drag types: standarduser || sambagroup || sambaguest
	var dragType = e.dataTransfer.getData('type');

	// drag targets: fullaccess || readonly || noaccess
	var dragTarget = e.target.parentNode.id.substr(18);

	// hide "drag here" container
	document.getElementById('samba_shares_drag_' + dragTarget).style.display = 'none';

	// hide old spacer
	// document.getElementById('sambaspacer_' + dragUser).style.display = 'none';

	// add user to dropzone
	var dropZone = document.getElementById('samba_shares_drop_' + dragTarget);
	var dragTypePad = (dragType + "xxxx").substr(0, 12);
	var dropSource = document.getElementById(dragTypePad + '_' + dragName);
	dropZone.appendChild(dropSource);

	// save group and user
	document.getElementById('samba_shares_name').value = dragName;
	document.getElementById('samba_shares_type').value = dragType;
	document.getElementById('samba_shares_target').value = dragTarget;

	// submit form
	document.getElementById('samba_shares_dragdropform').submit();
}

function drop_grouplist(e) {
	// prevent default action
	e.preventDefault();

	// fetch drag information
	var dragName = e.dataTransfer.getData('name');
	var dragType = e.dataTransfer.getData('type');
	var dragSource = e.dataTransfer.getData('source');

	// save name and type
	document.getElementById('samba_shares_name').value = dragName;
	document.getElementById('samba_shares_type').value = dragType;

	// submit form
	document.getElementById('samba_shares_dragdropform').submit();
}

function drop_users(e) {
	// TODO: sanity checks

	// prevent default action
	e.preventDefault();

	// fetch drag information
	var dragUser = e.dataTransfer.getData('user');
	var dragGroup = e.dataTransfer.getData('group');
	var dragTarget = e.target.parentNode.id.substr(17);

	// hide "drag here" container
	document.getElementById('samba_users_drag_' + dragTarget).style.display = 'none';

	// hide old spacer
	document.getElementById('sambaspacer_' + dragUser).style.display = 'none';

	// add user to dropzone
	var dropzone = document.getElementById('samba_users_drop_' + dragTarget);
	dropzone.appendChild(document.getElementById('sambauserxxx_' + dragUser));

	// save group and user
	document.getElementById('samba_users_user').value = dragUser;
	document.getElementById('samba_users_oldgroup').value = dragGroup;
	document.getElementById('samba_users_newgroup').value = dragTarget;

	// submit form
	document.getElementById('samba_users_dragdropform').submit();
}

/* Access->Users page */

function hideClasses() {
	var sambaSelect = document.querySelectorAll('div.samba_select');
	for (var i = 0, l = sambaSelect.length; i < l; i++) {
		sambaSelect[i].style.display = 'none';
	};
}

function addUser(e) {
	hideClasses();
	var selectedGroup = e.id.substr(9);
	if (selectedGroup) {
		document.getElementById('samba_adduserbox_' + selectedGroup).style.display = 'block';
		document.getElementById('samba_adduser_' + selectedGroup).focus();
	};
}

function modifyUser(e) {
	hideClasses();
	var selectedUser = e.id.substr(13);
	var selectedGroup = e.parentNode.id.substr(13);
	if (selectedUser && selectedGroup) {
		document.getElementById('samba_modify_username_' + selectedGroup).value = selectedUser;
		document.getElementById('samba_select_' + selectedGroup).style.display = 'block';
		document.getElementById('samba_modify_password_' + selectedGroup).focus();
	};
}

function sambaAddUser(e) {
	// TODO: make unique
	var userGroup = e.id.substr(19);
	var userName = document.getElementById('samba_adduser_' + userGroup).value;
	var userPassword = prompt('Samba password for ' + userName, '');
	if (!userPassword) {
		return false;
	}
	// save user password
	document.getElementById('samba_adduserpassword_' + userGroup).value = userPassword;
	// proceed with form submit
	return true;
}
