{{--
    Shared script tail for the agent portal layouts (agent-order + agent-pos).
    Vendor libraries that depend on jQuery (select2, sweetalert2, …) are pushed
    by the layout onto the 'vendor-scripts' stack so they load in the right order.
--}}
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>
@stack('vendor-scripts')
<script src="{{ asset('assets/js/brand-theme.js') }}"></script>
@auth('customer')
    <script>
        window.shopRoutes = {
            shop: @json(route('agent-order.index')),
            variants: @json(route('agent-order.products.variants')),
            cartAdd: @json(route('agent-order.cart.add')),
            cartUpdate: @json(route('agent-order.cart.update')),
            cartRemove: @json(route('agent-order.cart.remove')),
            csrf: @json(csrf_token()),
        };
        window.shopCheckoutUrl = @json(route('agent-order.checkout'));
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': window.shopRoutes.csrf } });
    </script>
    <script src="{{ asset('assets/js/shop.js') }}"></script>
@endauth
