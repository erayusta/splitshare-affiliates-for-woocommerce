<?php
/**
 * Generic ortaklık e-postası (WC_Email). İçerik SSA_Emails::body_paragraphs() üretir.
 */

defined( 'ABSPATH' ) || exit;

class SSA_Email extends WC_Email {

	/** @var string */
	public $ssa_id;

	/** @var array */
	private $def;

	/** @var SSA_Partner|null */
	public $partner = null;

	/** @var mixed */
	public $extra = null;

	public function __construct( $id, array $def ) {
		$this->ssa_id         = $id;
		$this->def            = $def;
		$this->id             = 'ssa_' . $id;
		$this->title          = $def['title'];
		$this->description    = $def['title'];
		$this->customer_email = ( 'partner' === $def['to'] );
		$this->template_html  = 'emails/notification.php';
		$this->template_plain = 'emails/plain/notification.php';
		$this->template_base  = SSA_PATH . 'templates/';
		$this->placeholders   = array( '{program}' => '', '{code}' => '', '{partner_name}' => '' );
		parent::__construct();
		if ( 'admin' === $def['to'] ) {
			$this->recipient = $this->get_option( 'recipient', get_option( 'admin_email' ) );
		}
	}

	public function get_default_subject() {
		return $this->def['subject'];
	}

	public function get_default_heading() {
		return $this->def['heading'];
	}

	public function trigger( $partner, $extra = null ) {
		if ( ! $partner instanceof SSA_Partner ) {
			return;
		}
		$this->setup_locale();
		$this->partner = $partner;
		$this->extra   = $extra;
		$this->placeholders['{program}']      = SSA_Settings::get( 'program_name' );
		$this->placeholders['{code}']         = $partner->code;
		$this->placeholders['{partner_name}'] = $partner->display_name();
		if ( 'partner' === $this->def['to'] ) {
			$this->recipient = $partner->email();
		}
		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}
		$this->restore_locale();
	}

	public function paragraphs() {
		return SSA_Emails::body_paragraphs( $this->ssa_id, $this->partner, $this->extra );
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, array( 'email' => $this, 'email_heading' => $this->get_heading(), 'paragraphs' => $this->paragraphs(), 'sent_to_admin' => 'admin' === $this->def['to'], 'plain_text' => false ), 'splitshare-affiliates/', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, array( 'email' => $this, 'email_heading' => $this->get_heading(), 'paragraphs' => $this->paragraphs(), 'sent_to_admin' => 'admin' === $this->def['to'], 'plain_text' => true ), 'splitshare-affiliates/', $this->template_base );
	}

	public function init_form_fields() {
		parent::init_form_fields();
		if ( 'admin' === $this->def['to'] ) {
			$this->form_fields = array_merge( array(
				'recipient' => array( 'title' => __( 'Recipient(s)', 'woocommerce' ), 'type' => 'text', 'default' => get_option( 'admin_email' ), 'desc_tip' => true ),
			), $this->form_fields );
		}
	}
}
