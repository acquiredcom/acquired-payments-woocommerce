/**
 * Admin settings.
 */

const environmentSelect = document.querySelector('#woocommerce_acfw_environment');

if (environmentSelect) {
  environmentSelect.addEventListener('change', function() {
    const environment = this.value;
    const productionFields = document.querySelectorAll('#woocommerce_acfw_company_id_production, #woocommerce_acfw_app_id_production, #woocommerce_acfw_app_key_production, #woocommerce_acfw_signing_key_production');
    const stagingFields = document.querySelectorAll('#woocommerce_acfw_company_id_staging, #woocommerce_acfw_app_id_staging, #woocommerce_acfw_app_key_staging, #woocommerce_acfw_signing_key_staging');

    productionFields.forEach(field => {
      field.closest('tr').style.display = environment === 'production' ? 'table-row' : 'none';
    });

    stagingFields.forEach(field => {
      field.closest('tr').style.display = environment === 'staging' ? 'table-row' : 'none';
    });

    const environmentLinks = document.querySelectorAll('.acfw-env-link');

    environmentLinks.forEach(link => {
      link.href = link.dataset[`envHref${environment.charAt(0).toUpperCase() + environment.slice(1)}`];
    });
  });

  environmentSelect.dispatchEvent(new Event('change'));
}
