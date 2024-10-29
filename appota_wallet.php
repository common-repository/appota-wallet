<?php
/**
 * Plugin Name: Appota Wallet
 * Plugin URI: vi.appota.com
 * Description: Thanh toán với Ví Appota
 * - Tích hợp thanh toán qua appotapay.com cho các website bán hàng có đăng ký API.
 * - Thực hiện lấy thông tin tài khoản người bán                             *
 *   danh sách các phương thức thanh toán ngân hàng qua email
 * - Gửi thông tin thanh toán tới appotapay.com để xử lý việc thanh toán.
 * - Xác thực tính chính xác của thông tin được gửi về từ appotapay.com
 * Version: 1.0
 * Author: Appota
 * Author URI: http://appotapay.com/
 * License: Appotapay.com
 */
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly
include(plugin_dir_path(__FILE__) . 'call_api.php');

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    //Create class after the plugins are loaded
    add_action('plugins_loaded', 'init_appota_wallet_class');

    //Init payment gateway class
    function init_appota_wallet_class()
    {

        //Defining class gateway
        function add_appota_wallet_class($methods)
        {
            $methods[] = 'WC_Appota_Wallet';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'add_appota_wallet_class');

        class WC_Appota_Wallet extends WC_Payment_Gateway
        {

            public function __construct()
            {

                // Đặt ID cho phương thức thanh toán (cần Unique)
                $this->id = 'appota_wallet';
                // Đặt language cho phương thức thanh toán
                $this->lang = 'vi';
                // Đặt Icon trong cấu hình cho phương thức
                $this->icon = plugins_url('images/appota-plugin-icon.png', __FILE__);
                // Không hiện trường ngoài thanh toán người dùng
                $this->has_fields = false;
                // Tên phương thức thanh toán
                $this->method_title = __('Appota Wallet', 'woocommerce');
                // Mô tả phương thức thanh toán
                $this->method_description = "Phương thức thanh toán an toàn với chi phí thấp qua cổng thanh toán Appotapay.com";
                // Có dùng SSL verify khi gọi API Appota hay không. True: có, False: không
                $this->ssl_verify = False;

                // Gọi init_form_fields theo chuẩn Woocommerce
                $this->init_form_fields();
                // Thực hiện chuyển cấu hình init_form_fields thành form cấu hình trong admin
                $this->init_settings();

                // Lấy thông tin tiêu đề phương thức thanh toán
                $this->title = $this->get_option('title');
                // Mô tả phương thức thanh toán
                $this->description = $this->get_option('description');
                // Lấy tên cửa hàng bán
                $this->appota_merchant_name = $this->get_option('appota_merchant_name');
                // Lấy api key được lưu trong cấu hình
                $this->appota_api_key = $this->get_option('appota_api_key');
                // Lấy api secret được lưu trong cấu hình
                $this->appota_api_secret = $this->get_option('appota_api_secret');
                // Lấy api secret được lưu trong cấu hình
                $this->appota_api_private_key = $this->get_option('appota_api_secret');
                // Lấy tên log file.
                $this->appota_log_file = $this->get_option('appota_log_file');

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_api_wc_appota_wallet', array($this, 'payment_complete'));
                if (!$this->is_valid_for_use()) {
                    $this->enabled = false;
                }
            }

            /**
             * Cấu hình các trường dữ liệu cần lưu trong quản trị
             */
            public function init_form_fields()
            {
                parent::init_form_fields();
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Sử dụng phương thức', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Đồng ý', 'woocommerce'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Tiêu đề', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Tiêu đề của phương thức thanh toán bạn muốn hiển thị cho người dùng.', 'woocommerce'),
                        'default' => __('Appota Wallet', 'woocommerce'),
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => __('Mô tả phương thức thanh toán', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Mô tả của phương thức thanh toán bạn muốn hiển thị cho người dùng.', 'woocommerce'),
                        'default' => __('Thanh toán an toàn với Appota Wallet. Thực hiện thanh toán với thẻ cào hoặc tài khoản ngân hàng trực tuyến', 'woocommerce')
                    ),
                    'account_config' => array(
                        'title' => __('Cấu hình tài khoản', 'woocommerce'),
                        'type' => 'title',
                        'description' => '',
                    ),
                    'appota_merchant_name' => array(
                        'title' => __('Tên cửa hàng', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Tên cửa hàng của người bán hàng sử dụng cổng thanh toán Appota Pay.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_api_key' => array(
                        'title' => __('Appota API Key', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('API Key của tài khoản.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_api_secret' => array(
                        'title' => __('Appota API Secret', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('API Secret của tài khoản.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_api_private_key' => array(
                        'title' => __('Appota API Private Key', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Private Key để verify khi gọi API lên hệ thống.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_log_file' => array(
                        'title' => __('Tên file lưu log', 'woocommerce'),
                        'type' => 'text',
                        'description' => sprintf(__('Tên file lưu trữ log trong quá trình thực hiện thanh toán bằng cổng Appota Wallet, truy cập file log <code>woocommerce/logs/appota-wallet-%s.log</code>', 'woocommerce'), date("d-m-Y")),
                        'default' => 'appota-wallet',
                        'desc_tip' => true,
                    ),
                );
            }

            /**
             * Kiểm tra xem loại tiền tệ hệ thống dùng thanh toán có phù hợp với cổng thanh toán không
             *
             * @access public
             * @return bool
             */
            function is_valid_for_use()
            {
                if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_appota_supported_currencies', array('VND'))))
                    return false;
                return true;
            }

            /**
             * Admin Panel Options
             * - Hiển thị quản trị cấu hình cho plugins
             *
             * @since 1.0.0
             */
            public function admin_options()
            {
                ?>
                <h3>
                    <?php _e('Thanh toán Appota Pay', 'woocommerce'); ?>

                    <div class="pull-right">Hướng dẫn cấu hình hệ thống chi tiết <a href="https://github.com/appotapay/appotapay-wordpress">tại đây</a></div>
                </h3>
                <strong><?php _e('Đảm bảo an toàn tuyệt đối cho mọi giao dịch.', 'woocommerce'); ?></strong>
                <?php if ($this->is_valid_for_use()) : ?>
                <table class="form-table">
                    <?php
                    // Generate the HTML For the settings form.
                    $this->generate_settings_html();
                    ?>
                </table><!--/.form-table-->

            <?php else : ?>
                <div class="inline error">
                    <p>
                        <strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('Phương thức thanh toán Appota Pay chỉ hỗ trợ tiền Việt Nam Đồng trên gian hàng của bạn. Xin hãy đổi loại tiền thanh toán thành Việt Nam Đồng', 'woocommerce'); ?>
                    </p>
                </div>
                <?php
            endif;
            }

            public function process_payment($order_id)
            {
//                include(plugin_dir_path(__FILE__) . 'appota_logger.php');
//                $logger = new WC_Appota_Logger();

                $order = new WC_Order($order_id);
                // Request sang Appota Wallet để lấy đường dẫn trang thanh toán
                $result = $this->receive_payment_url($order);

                if (empty($result)) {
                    $message = "Không nhận được thông tin trả về!";
//                    $logger->writeLog("Failure: " . $message);
                    wc_add_notice(__('Payment error:', 'woothemes') . " " . $message, 'error');
                    return;
                }
                // Nếu có lỗi, hiện thông báo lỗi thanh toán
                if ($result['error_code'] != 0) {
//                    $logger->writeLog("Failure: " . $result['message']);
                    wc_add_notice(__('Payment error:', 'woothemes') . " " . $result['message'], 'error');
                    return;
                }
                // Nếu không có lỗi, chuyển hướng url sang trang thanh toán của Appota
                $appota_wallet_url = $result['data']['options'][0]['url'];
//                $logger->writeLog("Success: Redirect Payment Url -> " . $appota_wallet_url);

                global $woocommerce;
                $woocommerce->cart->empty_cart();
                return array(
                    'result' => 'success',
                    'redirect' => $appota_wallet_url
                );
            }

            /**
             * Lấy thông tin đơn hàng, gửi sang cổng thanh toán để nhận đường dẫn redirect
             * @param mixed $order
             * @internal param developer_trans_id       Mã đơn hàng
             * @internal param amount                   Giá trị đơn hàng
             * @internal param client_ip                Phí vận chuyển
             * @internal param success_url              Url trả về khi thanh toán thành công
             * @internal param error_url                Url trả về khi hủy thanh toán
             * @access public
             * @return array
             */
            function receive_payment_url($order)
            {
                // Tạo đường dẫn nhận kết quả trả về sau khi thanh toán thành công
                $url_success = get_bloginfo('wpurl') . "/wc-api/WC_Appota_Wallet";
                // Tạo đường dẫn nhận kết quả trả về sau khi thanh toán bị dừng
                $url_cancel = $order->get_cancel_order_url();

                $params = array(
                    'developer_trans_id' => 'AP-' . $order->id . '-' . rand(1000, 9999) . 'W',
                    'amount' => strval($order->order_total),
                    'client_ip' => $this->auto_reverse_proxy_pre_comment_user_ip(),
                    'success_url' => $url_success,
                    'error_url' => $url_cancel
                );
                $config = array();
                $config['api_key'] = $this->appota_api_key;
                $config['lang'] = $this->lang;

                // Gọi resful API của Appota Pay
                $call_api = new Appota_Call_Api($config);
                $result = $call_api->getPaymentUrl($params);

                return $result;
            }

            function payment_complete()
            {
                global $woocommerce;
                include(plugin_dir_path(__FILE__) . 'appota_receiver.php');
//                include(plugin_dir_path(__FILE__) . 'appota_logger.php');

                $receiver = new WC_Appota_Receiver();
//                $logger = new WC_Appota_Logger();

                $check_valid_request = $receiver->checkValidRequest($_GET);
                if ($check_valid_request['error_code'] == 0) {
                    $check_valid_order = $receiver->checkValidOrder($_GET);

                    if ($check_valid_order['error_code'] == 0) {
                        $developer_trans_id = $_GET['developer_trans_id'];
                        $tran_id_arr = explode('-', $developer_trans_id);
                        $order_id = (int) $tran_id_arr[1];
                        $transaction_id = $_GET['transaction_id'];
                        $total_amount = floatval($_GET['amount']);
                        $order = new WC_Order($order_id);
                        $comment_status = 'Thực hiện thanh toán thành công với đơn hàng ' . $order_id . '. Giao dịch hoàn thành. Cập nhật trạng thái cho đơn hàng thành công';
                        $order->add_order_note(__($comment_status, 'woocommerce'));
                        $order->payment_complete();
                        $order->update_status('completed');

                        $order->update_meta_data('transaction_id', $transaction_id);
                        update_post_meta($order_id, 'appota_wallet_transaction_id', $transaction_id);
                        $woocommerce->cart->empty_cart();
                        $order_status = 'complete';

                        $message = "Appota Pay xác nhận đơn hàng: [Order ID: {$order_id}] - [Transaction ID: {$transaction_id}] - [Total: {$total_amount}] - [{$order_status}]";
//                        $logger->writeLog($message);

                        wp_redirect(add_query_arg('utm_nooverride', '1', $this->get_return_url($order)));
                    } else {
                        $message = "Mã Lỗi: {$check_valid_order['error_code']} - Message: {$check_valid_order['message']}";
//                        $logger->writeLog($message);

                        $redirect_url = add_query_arg('wc_error', urlencode($message . " Hãy thanh toán lại!"), '/thanh-toan/');
                        wp_redirect($redirect_url);
                    }
                } else {
                    $message = "Mã Lỗi: {$check_valid_request['error_code']} - Message: {$check_valid_request['message']}";
//                    $logger->writeLog($message);
                    $redirect_url = add_query_arg('wc_error', urlencode($message . " Hãy thanh toán lại!"), '/thanh-toan/');
                    wp_redirect($redirect_url);
                }
            }

            function auto_reverse_proxy_pre_comment_user_ip()
            {
                $REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
                if (!empty($_SERVER['X_FORWARDED_FOR'])) {
                    $X_FORWARDED_FOR = explode(',', $_SERVER['X_FORWARDED_FOR']);
                    if (!empty($X_FORWARDED_FOR)) {
                        $REMOTE_ADDR = trim($X_FORWARDED_FOR[0]);
                    }
                } /*
                 * Some php environments will use the $_SERVER['HTTP_X_FORWARDED_FOR'] 
                 * variable to capture visitor address information.
                 */ elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $HTTP_X_FORWARDED_FOR = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                    if (!empty($HTTP_X_FORWARDED_FOR)) {
                        $REMOTE_ADDR = trim($HTTP_X_FORWARDED_FOR[0]);
                    }
                }
                return preg_replace('/[^0-9a-f:\., ]/si', '', $REMOTE_ADDR);
            }

        }

    }

    add_action( 'woocommerce_admin_order_data_after_order_details', 'appota_wallet_transaction_id_order_meta', 10, 1 );

    function appota_wallet_transaction_id_order_meta($order){
        echo '<p class="form-field form-field-wide"><label>'.__('Appota Wallet Transaction id').': </label>' . get_post_meta( $order->id, 'appota_wallet_transaction_id', true ). '</p>';
    }

} else {

    /**
     * Thông báo cài đặt hoặc kích hoạt Woocommerce nếu plugin chưa được cài đặt hoặc kích hoạt
     */
    function appota_wallet_missing_woocommerce_notice()
    {
        $class = 'notice notice-error';
        $message = __('Hệ thống chưa cài đặt hoặc kích hoạt plugin Woocommerce! Bạn cần cài đặt hoặc kích hoạt Woocommerce để sử dụng plugin Appota Wallet', 'woocomerce-missing-plugin');

        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }

    add_action('admin_notices', 'appota_wallet_missing_woocommerce_notice');
}
