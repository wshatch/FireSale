<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Currency model
 *
 * @author		Jamie Holdroyd
 * @author		Chris Harvey
 * @package		FireSale\Core\Models
 *
 */
class Currency_m extends MY_Model
{

    protected $cache = array();

    /**
     * Loads the parent constructor and gets an
     * instance of CI.
     *
     * @return void
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
        $this->load->driver('Streams');
    }

    public function get($id = 1)
    {

        // Check cache
        if ( array_key_exists($id, $this->cache) ) {
            return $this->cache[$id];
        }

        // Variables
        $stream = $this->streams->streams->get_stream('firesale_currency', 'firesale_currency');
        $row    = $this->row_m->get_row($id, $stream, false);

        // Check it's valid
        if ($row) {

            // Format price, just incase
            $row->cur_format = html_entity_decode($row->cur_format);
            $row->symbol     = str_replace('&Acirc;', '', htmlspecialchars(str_replace('{{ price }}', '', $row->cur_format)));
            $row->symbol     = html_entity_decode($row->symbol, NULL, 'UTF-8');

            // Add to cache
            $this->cache[$id] = $row;

            return $row;
        }

        // Nothing?
        return FALSE;
    }

    public function get_symbol($id = 1)
    {

        // Variables
        $currency = $this->get($id);

        return str_replace('{{ price }}', '', $currency->symbol);
    }

    public function can_delete($currency)
    {

        // Get usage count
        $query = $this->db->where('currency', $currency)
                          ->get('firesale_orders');

        // return
        return ( $query->num_rows() || $currency == 1 ? false : true );
    }

    public function format_price($price, $rrp, $tax_id = NULL, $currency = NULL)
    {
        // Get currency ID
        if ( $this->session->userdata('currency') AND $currency == NULL ) {
            $currency = $this->session->userdata('currency');
        }

        // Get currency data
        $currency = $this->get(( $currency != NULL ? $currency : 1 ));

        // Check valid option
        if ( ! is_object($currency) ) {
            // Get default
            $currency = $this->get();
        }

        $query = $this->db->get_where('firesale_taxes_assignments', array(
            'tax_id'      => $tax_id,
            'currency_id' => $currency->id
        ));

        if ($query->num_rows()) {
            $currency->cur_tax = $query->row()->value;
        }

        // Add symbol
        $currency->symbol = str_replace('&Acirc;', '', htmlentities(str_replace('{{ price }}', '', $currency->cur_format)));

        // Perform conversion
        $tax_mod   = 1 + ( $currency->cur_tax / 100 );
        $rrp_tax   = ( $rrp   * $currency->exch_rate );
        $rrp       = ( $rrp   * $currency->exch_rate ) * $tax_mod;
        $price_tax = ( $price * $currency->exch_rate );
        $price     = ( $price * $currency->exch_rate ) * $tax_mod;

        // Format prices
        $rrp_f       = $this->format_string($rrp, $currency);       // RRP With tax
        $rrp_tax_f   = $this->format_string($rrp_tax, $currency);   // RRP Without tax
        $price_f     = $this->format_string($price, $currency);     // With tax
        $price_tax_f = $this->format_string($price_tax, $currency); // Without tax

        // Round prices (if required)
        $rrp_r       = $this->format_string($rrp, $currency, TRUE, FALSE, FALSE);       // RRP With tax
        $rrp_tax_r   = $this->format_string($rrp_tax, $currency, TRUE, FALSE, FALSE);   // RRP Without tax
        $price_r     = $this->format_string($price, $currency, TRUE, FALSE, FALSE);     // With tax
        $price_tax_r = $this->format_string($price_tax, $currency, TRUE, FALSE, FALSE); // Without tax

        // Prepare return
        $return = array(
                    'currency'            => $currency,
                    'rrp_tax'             => $rrp_tax,
                    'rrp_tax_formatted'   => $rrp_tax_f,
                    'rrp_tax_rounded'     => $rrp_tax_r,
                    'rrp'                 => $rrp,
                    'rrp_formatted'       => $rrp_f,
                    'rrp_rounded'         => $rrp_r,
                    'price_tax'           => $price_tax,
                    'price_tax_formatted' => $price_tax_f,
                    'price_tax_rounded'   => $price_tax_r,
                    'price'               => $price,
                    'price_formatted'     => $price_f,
                    'price_rounded'       => $price_r
                  );

        return $return;
    }

    public function format_string($price, $currency, $fix = TRUE, $apply_tax = FALSE, $format = TRUE)
    {
        // Format initial value
        if ($fix) {
            switch ($currency->cur_format_num) {
                case '1':
                    $price = ceil($price).'.00';
                break;

                case '2':
                    $price = ( round(( $price * 2 ), 0) / 2 );
                break;

                case '3':
                    $price = round($price).'.99';
                break;

                default:
                    $price = ( floor($price * 100) / 100 );
                break;
            }
        }

        // Apply tax if required
        if ($apply_tax) {
            $this->load->model('taxes_m');
            $percentage = $this->taxes_m->get_percentage($tax_band);

            $tax_mod = 1 - ($percentage / 100);

            $price = $price * (($percentage / 100) + 1);
        }

        if ( ! $format)
            return number_format($price, 2, $currency->cur_format_dec, $currency->cur_format_sep);

        // Just in case streams has added any extra formatting
        $currency->cur_format = html_entity_decode($currency->cur_format);

        // Format
        $formatted = number_format($price, 2, $currency->cur_format_dec, $currency->cur_format_sep);
        $formatted = str_replace('{{ price }}', $formatted, $currency->cur_format);
        $formatted = trim($formatted);

        // Return
        return $formatted;
    }

}
