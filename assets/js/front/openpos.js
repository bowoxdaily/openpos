/**
 * Created by anhvnit on 6/18/17.
 */
document.addEventListener('visibilitychange', function(){
    if(document.visibilityState == 'visible'){
        window.focus(); 
        if(document.getElementById("scan-barcode-input"))
        {
            document.getElementById("scan-barcode-input").focus();
        }
        
    }
});
document.addEventListener('readystatechange', event => {
    
    switch (document.readyState) {
        case "complete":
            setTimeout(function() { 
                const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
                if (!isMobile) {
                    if(document.getElementById("scan-barcode-input"))
                    {
                        document.getElementById("scan-barcode-input").focus();
                    }
                }
                
            }, 500);
            
            break;
    }
});