/* Page: Access->Settings */

function sambaManualSettings(o) {
    const iframe = document.getElementById('samba_settings_manualiframe');
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
