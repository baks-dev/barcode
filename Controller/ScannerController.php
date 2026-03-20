<?php
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

declare(strict_types=1);

namespace BaksDev\Barcode\Controller;


use BaksDev\Barcode\Forms\ScannerBarcodeDTO;
use BaksDev\Barcode\Forms\ScannerBarcodeForm;
use BaksDev\Barcode\Messenger\ScannerMessage;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Core\Type\UidType\Uid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
/*#[RoleSecurity('ROLE_BARCODE')]*/
final class ScannerController extends AbstractController
{
    #[Route('/scanner', name: 'scanner', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MessageDispatchInterface $messageDispatch
    ): Response
    {
        // Форма
        $form = $this
            ->createForm(
                ScannerBarcodeForm::class,
                $ScannerDTO = new ScannerBarcodeDTO(),
                ['action' => $this->generateUrl('barcode:scanner')],
            )
            ->handleRequest($request);


        if($form->isSubmitted() && $form->isValid())
        {
            $this->refreshTokenForm($form);

            /** Ошибка при сканировании QR */
            if(false === Uid::isUid($ScannerDTO->getCode()))
            {
                return $this->render(
                    module: 'barcode',
                    dir: '/scanner',
                    file: 'error.html.twig',
                );
            }

            $ScannerMessage = new ScannerMessage($ScannerDTO->getCode());
            $messageDispatch->dispatch($ScannerMessage);


            return new Response('<div class="modal-dialog modal-dialog-centered modal-fullscreen" style="max-width: 800px;"><form name="product_delete_form" method="post" action="/admin/product/delete/019cd7de-bc2b-751b-a9c0-e7a785f8f2b2" class="w-100"><div class="modal-content p-3 border-bottom border-5 border-danger"><div class="modal-header"><h5 class="modal-title"> Удалить продукцию
</h5><div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
<span class="svg-icon svg-icon-2x"></span></div></div><div class="modal-body">
<h4> Вы уверены, что желаете удалить "'.$ScannerDTO->getCode().'"?
</h4><br><p> Убедитесь, что выбран именно тот объект, который нужно удалить, и нажмите Удалить. Если Вы не желаете удалять выбранный объект, нажмите кнопку Отмена.
</p></div><div class="modal-footer"><div class="flex-grow-1"> &nbsp;
</div><div class="d-flex gap-3"><button type="button" class="btn btn-light" data-bs-dismiss="modal"> Отмена
</button><button type="submit" id="product_delete_form_delete" name="product_delete_form[delete]" class="btn-danger btn"><span>Удалить</span><span class="spinner-border spinner-border-sm vertical-middle d-none"></span></button></div></div></div><input type="hidden" id="product_delete_form__token" name="product_delete_form[_token]" data-controller="csrf-protection" value="5e3dc5176feb9cc1153a7e444c.ObXw6i8YiYyJYWwQ9aeoxXt8rc0wARvc9NPYLWPuvEY.bPCSi1t_3vvmEgZJnv7fmkgR5ItbN0SEo-CQQw2q-gUJ76-MbW_I2eUOLQ"></form></div>');

            return new Response('********'.$ScannerDTO->getCode().'***********');

        }

        return $this->render(
            parameters: ['form' => $form->createView()],
            module: 'barcode',
            dir: '/scanner',
            file: 'template.html.twig',
        );
    }
}
