/*
 *  Copyright 2026.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */


let html5QrCode;
//const startButton = document.getElementById("start-button");
const qrReaderDiv = document.getElementById("qr-reader");
//const statusDiv = document.getElementById("status");

const scanner_barcode_form = document.forms.scanner_barcode_form;

executeFunc(function initHtml5Qrcode()
{

    if(typeof Html5Qrcode !== "function")
    {
        return false;
    }

    try
    {
        // Запрашиваем доступ к камере
        const stream = navigator.mediaDevices.getUserMedia({
            video : {facingMode : "environment"},
        });

        // Создаём сканер
        html5QrCode = new Html5Qrcode(
            "qr-reader",
            {
                fps : 10,
                qrbox : (viewfinderWidth, viewfinderHeight) =>
                {
                    // Делаем область сканирования на весь экран
                    const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                    return {
                        width : minEdge * 0.8,
                        height : minEdge * 0.8,
                    };
                },
                rememberLastUsedCamera : true,
                supportedScanTypes : [Html5QrcodeScanType.SCAN_TYPE_CAMERA],
                showTorchButtonIfSupported : true,
                showZoomSliderIfSupported : true,
                defaultZoomValueIfSupported : 1.0,
            },
        );

        qrReaderDiv.style.display = "block";
        //startButton.style.display = "none";

        //statusDiv.textContent += "Наведите камеру на QR-код...";

        const config = {
            fps : 20,
            qrbox : {width : 250, height : 250},
        };

        html5QrCode.start(
            {facingMode : "environment"},
            config,
            onScanSuccess,
        );

    }
    catch(err)
    {
        console.error(err);
        //statusDiv.textContent = "❌ Ошибка доступа к камере: " + err.message;
    }

    return true;

});


async function onScanSuccess(decodedText, decodedResult)
{

    // statusDiv.textContent += "/r/n"+decodedText;

    // Останавливаем сканер
    await html5QrCode.stop();
    qrReaderDiv.style.display = "none";
    //startButton.style.display = "block";


    // Отправляем на сервер
    await sendCodeToServer(decodedText);

    // statusDiv.textContent += "✅ Фото отправлено на сервер!";
}

// Функция отправки на сервер
async function sendCodeToServer(qrText)
{

    let modal = document.getElementById("modal");


    modal.innerHTML = "<div class=\"modal-dialog modal-dialog-centered modal-xl\">\n" +
        "        <div class=\"modal-content border-0 bg-transparent shadow-none\">\n" +
        "            <div class=\"d-flex justify-content-center w-100\">\n" +
        "                <button type=\"button\" class=\"btn btn-link w-100\" style=\"min-height: 500px;\" data-bs-dismiss=\"modal\">\n" +
        "                    <div class=\"spinner-border text-light\" role=\"status\">\n" +
        "                        <span class=\"visually-hidden\">Loading...</span>\n" +
        "                    </div>\n" +
        "                </button>\n" +
        "            </div>\n" +
        "        </div>\n" +
        "    </div>";


    bootstrap.Modal.getOrCreateInstance(modal).show();


    //bootstrap.Modal.getOrCreateInstance(modal).show();


    try
    {
        const data = new FormData(scanner_barcode_form);
        data.set(scanner_barcode_form.name + "[code]", qrText);

        const response = await fetch(scanner_barcode_form.action, {
                method : scanner_barcode_form.method,
                cache : "no-cache", // *default, no-cache, reload, force-cache, only-if-cached
                credentials : "same-origin", // include, *same-origin, omit
                headers : {
                    // 'X-Requested-With': 'XMLHttpChange'
                    "X-Requested-With" : "XMLHttpRequest",
                },
                redirect : "follow", // manual, *follow, error
                referrerPolicy : "no-referrer", // no-referrer, *no-referrer-when-downgrade, origin, origin-when-cross-origin, same-origin, strict-origin, strict-origin-when-cross-origin, unsafe-url
                body : data, // body data type must match "Content-Type" header
            }).then((response) =>
            {
                if(response.status === 302)
                {
                    window.location.reload();
                }

                if(response.status !== 200)
                {
                    return false;
                }

                return response.text();

            }).then((data) =>
            {

                modal.innerHTML = data;

                /* Сбрасываем содержимое модального окна при закрытии */
                modal.addEventListener("hidden.bs.modal", function(event)
                {
                    location.reload();
                });

                modal.querySelectorAll("form").forEach(function(forms)
                {

                    /* событие отправки формы */
                    forms.addEventListener("submit", function(event)
                    {
                        event.preventDefault();
                        submitModalForm(forms);
                        return false;
                    });
                });

                let lazy = document.createElement("script");
                lazy.src = "/assets/" + $version + "/js/lazyload.min.js?v=" + Date.now();
            })
        ;


        //const result = await response.json();
        //const result = await response.body;


        //myOffcanvas.innerHTML = data;

        ///document.getElementById("modal-content");

        //statusDiv.textContent += "/r/n"+"Сервер ответил:";
        //statusDiv.textContent += "/r/n"+result;

    }
    catch(error)
    {
        console.error("Ошибка отправки:", error);
        //statusDiv.textContent = "❌ Ошибка отправки на сервер";
        // statusDiv.textContent = "/r/n"+error;
    }

}