@auth('customer')
    <div class="offcanvas offcanvas-end shop-cart-offcanvas" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title" id="cartOffcanvasLabel">Keranjang</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
        </div>
        <div class="offcanvas-body d-flex flex-column p-0">
            <div class="flex-grow-1 overflow-auto p-3" id="cartItemsList">
                @include('agent.order._cart-items', ['cart' => $navCart, 'summary' => $navCartSummary])
            </div>
            <div class="border-top p-3 bg-light" id="cartFooter">
                @include('agent.order._cart-footer', ['summary' => $navCartSummary])
            </div>
        </div>
    </div>
@endauth
