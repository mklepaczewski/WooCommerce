<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Activation;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Package;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Container\ContainerInterface;

use function Mollie\WooCommerce\mollie_wc_plugin_autoload;

class ActivationModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    private $baseFile;
    private $pluginVersion;

    /**
     * ActivationModule constructor.
     */
    public function __construct($baseFile, $pluginVersion)
    {
        $this->baseFile = $baseFile;
        $this->pluginVersion = $pluginVersion;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return bool
     */
    public function run(ContainerInterface $container): bool
    {
        add_action(
            'init',
            [$this, 'pluginInit']
        );
        $this->declareCompatibleWithHPOS();
        $this->handleTranslations();
        $this->mollieWcNoticeApiKeyMissing();
        $this->appleValidationFileRewriteRules();
        return true;
    }

    /**
     *
     */
    public function initDb()
    {
        global $wpdb;
        global $EZSQL_ERROR;
        $wpdb->mollie_pending_payment = $wpdb->prefix . SharedDataDictionary::PENDING_PAYMENT_DB_TABLE_NAME;
        if (get_option(SharedDataDictionary::DB_VERSION_PARAM_NAME, '') !== SharedDataDictionary::DB_VERSION) {
            $pendingPaymentConfirmTable = $wpdb->prefix . SharedDataDictionary::PENDING_PAYMENT_DB_TABLE_NAME;
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $pendingPaymentConfirmTable)) !== $pendingPaymentConfirmTable) {
                $sql = "
					CREATE TABLE " . $pendingPaymentConfirmTable . " (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    post_id bigint NOT NULL,
                    expired_time int NOT NULL,
                    PRIMARY KEY id (id)
                );";
                dbDelta($sql);

                /**
                 * Remove redundant 'DESCRIBE *__mollie_pending_payment' error so it doesn't show up in error logs
                 */
                array_pop($EZSQL_ERROR);
            }
            update_option(SharedDataDictionary::DB_VERSION_PARAM_NAME, SharedDataDictionary::DB_VERSION);
        }
    }

    /**
     *
     */
    public function handleTranslations(): void
    {
        add_action('core_upgrade_preamble', 'mollieDeleteWPTranslationFiles');
        add_filter(
            'site_transient_update_plugins',
            static function ($value) {
                if (isset($value->translations)) {
                    $i = 0;
                    foreach ($value->translations as $translation) {
                        if (
                            $translation["slug"]
                            === "mollie-payments-for-woocommerce"
                        ) {
                            unset($value->translations[$i]);
                        }
                        $i++;
                    }
                }

                return $value;
            }
        );
    }

    /**
     *
     */
    public function appleValidationFileRewriteRules(): void
    {
        $requestUri = isset($_SERVER['REQUEST_URI'])? filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL): '';
        if (strpos($requestUri, '.well-known/apple-developer-merchantid-domain-association') !== false) {
            $validationString = '7B227073704964223A2244394337463730314338433646324336463344363536433039393434453332323030423137364631353245353844393134304331433533414138323436453630222C2276657273696F6E223A312C22637265617465644F6E223A313536363930343337353936352C227369676E6174757265223A223330383030363039326138363438383666373064303130373032613038303330383030323031303133313066333030643036303936303836343830313635303330343032303130353030333038303036303932613836343838366637306430313037303130303030613038303330383230336533333038323033383861303033303230313032303230383463333034313439353139643534333633303061303630383261383634386365336430343033303233303761333132653330326330363033353530343033306332353431373037303663363532303431373037303663363936333631373436393666366532303439366537343635363737323631373436393666366532303433343132303264323034373333333132363330323430363033353530343062306331643431373037303663363532303433363537323734363936363639363336313734363936663665323034313735373436383666373236393734373933313133333031313036303335353034306130633061343137303730366336353230343936653633326533313062333030393036303335353034303631333032353535333330316531373064333133393330333533313338333033313333333233353337356131373064333233343330333533313336333033313333333233353337356133303566333132353330323330363033353530343033306331633635363336333264373336643730326436323732366636623635373232643733363936373665356635353433333432643530353234663434333131343330313230363033353530343062306330623639346635333230353337393733373436353664373333313133333031313036303335353034306130633061343137303730366336353230343936653633326533313062333030393036303335353034303631333032353535333330353933303133303630373261383634386365336430323031303630383261383634386365336430333031303730333432303030346332313537376564656264366337623232313866363864643730393061313231386463376230626436663263323833643834363039356439346166346135343131623833343230656438313166333430376538333333316631633534633366376562333232306436626164356434656666343932383938393365376330663133613338323032313133303832303230643330306330363033353531643133303130316666303430323330303033303166303630333535316432333034313833303136383031343233663234396334346639336534656632376536633466363238366333666132626266643265346233303435303630383262303630313035303530373031303130343339333033373330333530363038326230363031303530353037333030313836323936383734373437303361326632663666363337333730326536313730373036633635326536333666366432663666363337333730333033343264363137303730366336353631363936333631333333303332333038323031316430363033353531643230303438323031313433303832303131303330383230313063303630393261383634383836663736333634303530313330383166653330383163333036303832623036303130353035303730323032333038316236306338316233353236353663363936313665363336353230366636653230373436383639373332303633363537323734363936363639363336313734363532303632373932303631366537393230373036313732373437393230363137333733373536643635373332303631363336333635373037343631366536333635323036663636323037343638363532303734363836353665323036313730373036633639363336313632366336353230373337343631366536343631373236343230373436353732366437333230363136653634323036333666366536343639373436393666366537333230366636363230373537333635326332303633363537323734363936363639363336313734363532303730366636633639363337393230363136653634323036333635373237343639363636393633363137343639366636653230373037323631363337343639363336353230373337343631373436353664363536653734373332653330333630363038326230363031303530353037303230313136326136383734373437303361326632663737373737373265363137303730366336353265363336663664326636333635373237343639363636393633363137343635363137353734363836663732363937343739326633303334303630333535316431663034326433303262333032396130323761303235383632333638373437343730336132663266363337323663326536313730373036633635326536333666366432663631373037303663363536313639363336313333326536333732366333303164303630333535316430653034313630343134393435376462366664353734383138363839383937363266376535373835303765373962353832343330306530363033353531643066303130316666303430343033303230373830333030663036303932613836343838366637363336343036316430343032303530303330306130363038326138363438636533643034303330323033343930303330343630323231303062653039353731666537316531653733356235356535616661636234633732666562343435663330313835323232633732353130303262363165626436663535303232313030643138623335306135646436646436656231373436303335623131656232636538376366613365366166366362643833383038393064633832636464616136333330383230326565333038323032373561303033303230313032303230383439366432666266336139386461393733303061303630383261383634386365336430343033303233303637333131623330313930363033353530343033306331323431373037303663363532303532366636663734323034333431323032643230343733333331323633303234303630333535303430623063316434313730373036633635323034333635373237343639363636393633363137343639366636653230343137353734363836663732363937343739333131333330313130363033353530343061306330613431373037303663363532303439366536333265333130623330303930363033353530343036313330323535353333303165313730643331333433303335333033363332333333343336333333303561313730643332333933303335333033363332333333343336333333303561333037613331326533303263303630333535303430333063323534313730373036633635323034313730373036633639363336313734363936663665323034393665373436353637373236313734363936663665323034333431323032643230343733333331323633303234303630333535303430623063316434313730373036633635323034333635373237343639363636393633363137343639366636653230343137353734363836663732363937343739333131333330313130363033353530343061306330613431373037303663363532303439366536333265333130623330303930363033353530343036313330323535353333303539333031333036303732613836343863653364303230313036303832613836343863653364303330313037303334323030303466303137313138343139643736343835643531613565323538313037373665383830613265666465376261653464653038646663346239336531333335366435363635623335616532326430393737363064323234653762626130386664373631376365383863623736626236363730626563386538323938346666353434356133383166373330383166343330343630363038326230363031303530353037303130313034336133303338333033363036303832623036303130353035303733303031383632613638373437343730336132663266366636333733373032653631373037303663363532653633366636643266366636333733373033303334326436313730373036633635373236663666373436333631363733333330316430363033353531643065303431363034313432336632343963343466393365346566323765366334663632383663336661326262666432653462333030663036303335353164313330313031666630343035333030333031303166663330316630363033353531643233303431383330313638303134626262306465613135383333383839616134386139396465626562646562616664616362323461623330333730363033353531643166303433303330326533303263613032616130323838363236363837343734373033613266326636333732366332653631373037303663363532653633366636643266363137303730366336353732366636663734363336313637333332653633373236633330306530363033353531643066303130316666303430343033303230313036333031303036306132613836343838366637363336343036303230653034303230353030333030613036303832613836343863653364303430333032303336373030333036343032333033616366373238333531313639396231383666623335633335366361363262666634313765646439306637353464613238656265663139633831356534326237383966383938663739623539396639386435343130643866396465396332666530323330333232646435343432316230613330353737366335646633333833623930363766643137376332633231366439363466633637323639383231323666353466383761376431623939636239623039383932313631303639393066303939323164303030303331383230313863333038323031383830323031303133303831383633303761333132653330326330363033353530343033306332353431373037303663363532303431373037303663363936333631373436393666366532303439366537343635363737323631373436393666366532303433343132303264323034373333333132363330323430363033353530343062306331643431373037303663363532303433363537323734363936363639363336313734363936663665323034313735373436383666373236393734373933313133333031313036303335353034306130633061343137303730366336353230343936653633326533313062333030393036303335353034303631333032353535333032303834633330343134393531396435343336333030643036303936303836343830313635303330343032303130353030613038313935333031383036303932613836343838366637306430313039303333313062303630393261383634383836663730643031303730313330316330363039326138363438383666373064303130393035333130663137306433313339333033383332333733313331333133323335333535613330326130363039326138363438383666373064303130393334333131643330316233303064303630393630383634383031363530333034303230313035303061313061303630383261383634386365336430343033303233303266303630393261383634383836663730643031303930343331323230343230303864323631373363653335643465646134616139623866343763643439336639333034653763366631633866393239393238333361373537373736393438323330306130363038326138363438636533643034303330323034343733303435303232313030613439373261353733346466616362303131343162613366626238663062626665616162626233363161366536613839663666366361373132363165333039303032323032343439396561643530356238376664366331333134656135343963663431316339326632656437323837303533303738636562626638326462306561376633303030303030303030303030227D';
            nocache_headers();
            header('Content-Type: text/plain', true, 200);
            echo $validationString;
            exit;
        }
    }

    /**
     *
     */
    public function mollieWcNoticeApiKeyMissing()
    {
        //if test/live keys are in db return
        $liveKeySet = get_option('mollie-payments-for-woocommerce_live_api_key');
        $testKeySet = get_option('mollie-payments-for-woocommerce_test_api_key');
        $apiKeysSetted = $liveKeySet || $testKeySet;
        if ($apiKeysSetted) {
            return;
        }

        $notice = new AdminNotice();
        /* translators: Placeholder 1: Opening strong tag. Placeholder 2: Closing strong tag. Placeholder 3: Opening link tag to settings. Placeholder 4: Closing link tag.*/
        $message = sprintf(
            esc_html__(
                '%1$sMollie Payments for WooCommerce: API keys missing%2$s Please%3$s set your API keys here%4$s.',
                'mollie-payments-for-woocommerce'
            ),
            '<strong>',
            '</strong>',
            '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=mollie_settings')) . '">',
            '</a>'
        );

        $notice->addNotice('notice-error is-dismissible', $message);
    }

    protected function markUpdatedOrNew()
    {
        $dbVersionOption = get_option(SharedDataDictionary::DB_VERSION_PARAM_NAME, '');
        $dbPluginOption = get_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, '');

        if ($dbPluginOption === $this->pluginVersion) {
            return;
        }

        if (!$dbVersionOption && !$dbPluginOption) {
            update_option(SharedDataDictionary::NEW_INSTALL_PARAM_NAME, 'yes', true);
            update_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, $this->pluginVersion, true);
            return;
        }

        update_option(SharedDataDictionary::NEW_INSTALL_PARAM_NAME, 'no', true);
        update_option(SharedDataDictionary::PLUGIN_VERSION_PARAM_NAME, $this->pluginVersion, true);
    }

    /**
     *
     */
    public function pluginInit()
    {
        load_plugin_textdomain(
            'mollie-payments-for-woocommerce',
            false,
            dirname(plugin_basename($this->baseFile)) . '/languages/'
        );
        $this->markUpdatedOrNew();
        $this->initDb();
    }

    /**
     * @return void
     */
    protected function declareCompatibleWithHPOS(): void
    {
        $baseFile = $this->baseFile;
        add_action('before_woocommerce_init', static function () use ($baseFile) {
            if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
                FeaturesUtil::declare_compatibility('custom_order_tables', $baseFile, true);
            }
        });
    }
}
