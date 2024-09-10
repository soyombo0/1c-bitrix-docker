
        <?php include_once $_SERVER['DOCUMENT_ROOT'].'/include/feedback.php'; ?>
        <?php include_once $_SERVER['DOCUMENT_ROOT'].'/include/newsletter.php'; ?>
        <?php include_once $_SERVER['DOCUMENT_ROOT'].'/include/order.php'; ?>
        <?php include_once $_SERVER['DOCUMENT_ROOT'].'/include/footer/lang_variables.php'; ?>

        <div class="overlay hidden"></div>
        </main>
        <footer class="footer">
            <div class="container">
                <div class="footer__wrapper">
                    <div class="footer__top">
                        <?php $APPLICATION->IncludeComponent("bitrix:main.include","",Array(
                                "AREA_FILE_SHOW" => "file",
                                "PATH" => "/include/footer/logo_block.php",
                            )
                        );?>
                        <div class="footer__info">
                            <div class="footer__info-wrapper">
                                <div class="footer__block footer__block--product">
                                    <h3><?php echo $FOOTER_LANG_VARS['PRODUCTS']; ?></h3>
                                    <?php include_once $_SERVER['DOCUMENT_ROOT'].'/include/footer_blocks/catalog_nav_list.php'?>
                                </div>

                                <?$APPLICATION->IncludeComponent("bitrix:menu", "for_partners", array(
                                    "ROOT_MENU_TYPE" => LANGUAGE_ID."_partners",
                                    "MAX_LEVEL" => "1",
                                    "MENU_CACHE_TYPE" => "A",
                                    "CACHE_SELECTED_ITEMS" => "N",
                                    "MENU_CACHE_TIME" => "36000000",
                                    "MENU_CACHE_USE_GROUPS" => "Y",
                                    "MENU_CACHE_GET_VARS" => array(),
                                ),
                                    false
                                );?>
                            </div>


                            <?php $APPLICATION->IncludeComponent("bitrix:main.include","",Array(
                                    "AREA_FILE_SHOW" => "file",
                                    "PATH" => "/include/footer/".LANGUAGE_ID."_buttons_block.php",
                                )
                            );?>

                        </div>
                    </div>
                    <div class="footer__line"></div>

                    <?php $APPLICATION->IncludeComponent("bitrix:main.include","",Array(
                            "AREA_FILE_SHOW" => "file",
                            "PATH" => "/include/footer/".LANGUAGE_ID."_sub_footer_text.php",
                        )
                    );?>
                </div>
            </div>
        </footer>



        <?php $currentPage = $APPLICATION->GetCurPage(); ?>

        <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>

        <script src="<?php echo SITE_TEMPLATE_PATH?>/script/feedback.js"></script>
        <script src="<?php echo SITE_TEMPLATE_PATH?>/script/header.js"></script>
        <script src="<?php echo SITE_TEMPLATE_PATH?>/script/footer.js"></script>


        <?php if ($currentPage == '/promotion/' || $currentPage == '/en/promotion/'):?>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/promotion.js"></script>
        <?php endif;?>

        <?php if ($currentPage == '/korporativnye-tsennosti/' || $currentPage == '/en/korporativnye-tsennosti/'):?>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/korpotativnii.js"></script>
        <?php endif;?>

        <script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>

        <?php if ($currentPage == '/about/' || $currentPage == '/en/about/'):?>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/about.js"></script>
        <?php endif;?>

        <?php if ($currentPage == '/' || $currentPage == '/en/'):?>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/index.js"></script>
        <?php endif;?>


        <?php if ($currentPage == '/sertificates/' || $currentPage == '/en/sertificates/'):?>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/sertificates.js"></script>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/sertificate-modal.js"></script>
        <?php endif;?>


        <?php if (strpos($currentPage, '/catalog/') !== false): ?>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/catalog.js"></script>
            <script src="<?php echo SITE_TEMPLATE_PATH?>/script/product.js"></script>
        <?php endif;?>

        <script src='https://www.google.com/recaptcha/api.js?hl=<?php echo LANGUAGE_ID;?>'></script>

        <script src="<?php echo SITE_TEMPLATE_PATH?>/script/custom.js"></script>
        <script src="//code.jivosite.com/widget/prEQAqX0LC" async></script>
    </body>
</html>
