<!-- INCLUDE admin/header.tpl -->
<script type="text/javascript">
$(document).ready(function() {
    // Блок настроек
    initSettings();
});
// Изменение настроек
var initSettings = function(){
    var block = $(".settings");
    // Сохранение
    $("form .oActButtons a.save",block).live('click',function(){
        ajaxloader.show();
        $("form",block).submit(function(){
            $(this).ajaxSubmit({
                success: function(data) {
                    if (data=='ok') {
                        //block.find("form .oActButtons a.cancel").click();
                        // Уведомление о сохранении
                        notice('Настройки сайта успешно изменены');
                    } else {
                        block.find("form>div.oT2").html(data);
                        // редактор
                        initEditor($("form textarea.wiki",block),$("form",block));
                    }
                    ajaxloader.hide();
                }
            });
            return false;
        }).submit();
        return false;
    });
    // Отмена
    $("form .oActButtons a.cancel",block).live('click',function(){
        ajaxloader.show();
        block.find("form>div.oT2").load(block.find("form").attr('action'),{},function(data){
            // редактор
            initEditor($("form textarea.wiki",block),$("form",block));
            ajaxloader.hide();
        });
    });
    // редактор
    initEditor($("form textarea.wiki",block),$("form",block));
}
</script>
<div class="helpDescr"></div>
<div class="cats">
    <a href="admin/payment">Общие настройки</a>
    <a href="admin/payment/robokassa">Робокасса</a>
    <span style="background:#ffffff;">Yandex.Касса</span>
    <a href="admin/payment/paymaster">Paymaster</a>
    <a href="admin/payment/assetpayments">AssetPayments</a>
    <a href="admin/payment/paypal">PayPal</a>
</div>
<div class="catExt settings oEditor">
    <form action="admin/payment/yandex/settings" method="post">
        <div class="oT2 oEditItem"><!-- INCLUDE admin/payment/yandex/settings.tpl --></div>
    </form>
     <p><a href="https://bmshop.ru/help/v1/Payment/Yandex/" target="_blank">Инструкция по настройке Яндекс.Кассы</a></p>
</div>
<!-- INCLUDE admin/footer.tpl -->