
function checkV5000(o)
{
 switch (o.name)
 {
  case "compression":
   var warningbox = document.getElementById('upgradev5000warning');
   if (o.value == 'lz4')
    warningbox.className = 'normal';
   else
    warningbox.className = 'hidden';
   break;
  default:
   alert('v5000 check failed! ');
 }
}

