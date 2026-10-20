/**
 * 2026 Genisoft - PrestaShop Harness Engineering
 *
 * NOTICE OF LICENSE: MIT
 *
 * Refreshes the free-shipping bar after an AJAX cart update (add to cart on a product page,
 * quantity buttons or voucher on the cart page): the theme emits "updateCart", and the bar
 * container is rendered again by controllers/front/bar.php, so the amounts come from the same
 * PHP code. The container stays in the page while the bar is hidden, so the bar can come back.
 */
(function () {
  'use strict';

  if (typeof prestashop === 'undefined' || typeof prestashop.on !== 'function' || typeof window.fetch !== 'function') {
    return;
  }

  var CONTAINER_ID = 'genisoft-cart-threshold';
  var lastRequest = 0;

  prestashop.on('updateCart', function () {
    var container = document.getElementById(CONTAINER_ID);
    var url = container ? container.getAttribute('data-refresh-url') : '';
    if (!url) {
      return;
    }

    var request = ++lastRequest;

    fetch(url, {credentials: 'same-origin', cache: 'no-store'})
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }

        return response.text();
      })
      .then(function (html) {
        var current = document.getElementById(CONTAINER_ID);
        // Several quick cart updates: only the answer to the latest request is shown.
        if (request !== lastRequest || !current) {
          return;
        }

        var holder = document.createElement('div');
        holder.innerHTML = html;
        var fresh = holder.querySelector('#' + CONTAINER_ID);

        // No container in the answer: the bar was switched off in the back office meanwhile.
        if (fresh) {
          current.parentNode.replaceChild(fresh, current);
        } else {
          current.parentNode.removeChild(current);
        }
      })
      .catch(function () {
        // Keep the current bar; the next page load renders it again.
      });
  });
})();
