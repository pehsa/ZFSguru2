
/* Page: Access->Settings */

function sambaManualSettings(o)
{
 var iframe = document.getElementById('samba_settings_manualiframe');
 iframe.className = 'normal';
 var suffixUpperCase = (o.value + '').toUpperCase();
 var i = 0;
 var suffix = '';
 for (var i = 0; i < suffixUpperCase.length; i++)
 {
  if (suffixUpperCase.substr(i, 1) != ' ')
  {
   suffix += suffixUpperCase.substr(i, 1);
  };
 };
 if (iframe.className != 'normal')
 {
  iframe.className = 'normal';
 };
 iframe.src = 'http://www.samba.org/samba/docs/man/manpages-3/smb.conf.5.html#' + suffix;
}

