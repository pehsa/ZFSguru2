function extraTab(o) {
	let i;
// hide all tabs
    const divName = o.parentNode.id.substr(3);
    const extraTabDiv = document.getElementById('extratabbar');
    const extraTabChildren = extraTabDiv.getElementsByTagName('div');
    for (i = 0; i < extraTabChildren.length; i++) {
		if (extraTabChildren[i].id.substr(3).length > 0) {
			document.getElementById(extraTabChildren[i].id.substr(3)).setAttribute('class', 'hidden');
		}
	}
	// make chosen tab visible
	document.getElementById(divName).setAttribute('class', 'normal');
	// set class of passive extra tabs
    const extraTabButtons = o.parentNode.parentNode.getElementsByTagName('div');
    for (i = 0; i < extraTabButtons.length; i++) {
		if (extraTabButtons[i].className === 'et_tab_active') {
			extraTabButtons[i].className = 'et_tab';
		}
	}
	// set class of active extra tab
	o.parentNode.className = 'et_tab_active';
	// do not follow anchor link
	return false;
}
