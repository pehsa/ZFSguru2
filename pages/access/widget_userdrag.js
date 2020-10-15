/* drag & drop for Shares and Users tab */

function drag(e) {
	// drag data
	let target = e.target;
	let action = e.target.id.substr(0, 12);
	if (action === 'sambaimagexx') {
		target = e.target.parentNode;
		action = target.id.substr(0, 12);
	}
	let dragUser = target.id.substr(13);
	let dragSource = target.parentNode.id;
	e.dataTransfer.setData('source', dragSource);

	// page: Samba shares
	switch (action) {
		case 'standarduser':
			e.dataTransfer.setData('type', 'standarduser');
			e.dataTransfer.setData('name', dragUser);
			break;
		case 'useringroupx':
			e.dataTransfer.setData('type', 'useringroup');
			e.dataTransfer.setData('name', dragUser);
			break;
		case 'sambagroupxx':
			e.dataTransfer.setData('type', 'sambagroup');
			e.dataTransfer.setData('name', dragUser);
			break;
		case 'sambaguestxx':
			e.dataTransfer.setData('type', 'sambaguest');
			e.dataTransfer.setData('name', dragUser);
			break;
		case 'sambauserxxx':
			// save user and group of dragged object
			e.dataTransfer.setData('user', dragUser);
			e.dataTransfer.setData('group', target.parentNode.id.substr(13));
			break;
		case 'sambaimagexx':
			alert('UNUSED');
			// save user and group of dragged object
			e.dataTransfer.setData('user', dragUser);
			e.dataTransfer.setData('group', target.parentNode.parentNode.id.substr(13));
			break;
	}
}

function allowDrop_shares(e)
// TODO: make sambaguest deny drop for its own access list group
{
    const dragType = e.dataTransfer.getData('type');
    const dragSource = e.dataTransfer.getData('source').substr(11);
    const dragTarget = e.target.parentNode.id.substr(18);
    if (dragType === 'standarduser' || dragType === 'sambagroup' ||
		dragType === 'sambaguest' || dragType === 'useringroup') {
		if (dragSource !== dragTarget) {
			e.preventDefault();
		}
	}
}

function allowDrop_grouplist(e) {
    const dragType = e.dataTransfer.getData('type');
    const dragSource = e.dataTransfer.getData('source');
    if (dragType === 'standarduser' || dragType === 'sambagroup') {
		if (dragSource.substr(0, 11) === 'sambagroup_') {
			e.preventDefault();
		}
	}
}

function allowDrop_users(e) {
    const oldGroup = e.dataTransfer.getData('group');
    if (!oldGroup) {
		return false;
	}
    const newGroup = e.target.parentNode.id.substr(17);
    const dragObject = e.target.parentNode.id.substr(0, 17);
    if (dragObject === 'samba_users_drop_' || dragObject === 'samba_users_drag_') {
		if (newGroup !== oldGroup) {
			e.preventDefault();
		}
	}
}

function drop_shares(e)
// TODO: many
{
	// prevent default action
	e.preventDefault();

	// fetch drag information
    const dragName = e.dataTransfer.getData('name');

    // drag types: standarduser || sambagroup || sambaguest
    const dragType = e.dataTransfer.getData('type');

    // drag targets: fullaccess || readonly || noaccess
    const dragTarget = e.target.parentNode.id.substr(18);

    // hide "drag here" container
	document.getElementById('samba_shares_drag_' + dragTarget).style.display = 'none';

	// hide old spacer
	// document.getElementById('sambaspacer_' + dragUser).style.display = 'none';

	// add user to dropzone
    const dropZone = document.getElementById('samba_shares_drop_' + dragTarget);
    const dragTypePad = (dragType + "xxxx").substr(0, 12);
    const dropSource = document.getElementById(dragTypePad + '_' + dragName);
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
    const dragName = e.dataTransfer.getData('name');
    const dragType = e.dataTransfer.getData('type');

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
    const dragUser = e.dataTransfer.getData('user');
    const dragGroup = e.dataTransfer.getData('group');
    const dragTarget = e.target.parentNode.id.substr(17);

    // hide "drag here" container
	document.getElementById('samba_users_drag_' + dragTarget).style.display = 'none';

	// hide old spacer
	document.getElementById('sambaspacer_' + dragUser).style.display = 'none';

	// add user to dropzone
    const dropzone = document.getElementById('samba_users_drop_' + dragTarget);
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
    const sambaSelect = document.querySelectorAll('div.samba_select');
    let i = 0;
    const l = sambaSelect.length;
    for (; i < l; i++) {
		sambaSelect[i].style.display = 'none';
	}
}

function addUser(e) {
	hideClasses();
    const selectedGroup = e.id.substr(9);
    if (selectedGroup) {
		document.getElementById('samba_adduserbox_' + selectedGroup).style.display = 'block';
		document.getElementById('samba_adduser_' + selectedGroup).focus();
	}
}

function modifyUser(e) {
	hideClasses();
    const selectedUser = e.id.substr(13);
    const selectedGroup = e.parentNode.id.substr(13);
    if (selectedUser && selectedGroup) {
		document.getElementById('samba_modify_username_' + selectedGroup).value = selectedUser;
		document.getElementById('samba_select_' + selectedGroup).style.display = 'block';
		document.getElementById('samba_modify_password_' + selectedGroup).focus();
	}
}

function sambaAddUser(e) {
	// TODO: make unique
    const userGroup = e.id.substr(19);
    const userName = document.getElementById('samba_adduser_' + userGroup).value;
    const userPassword = prompt('Samba password for ' + userName, '');
    if (!userPassword) {
		return false;
	}
	// save user password
	document.getElementById('samba_adduserpassword_' + userGroup).value = userPassword;
	// proceed with form submit
	return true;
}
