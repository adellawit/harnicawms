<!-- Footer -->
<footer class="content-footer footer bg-footer-theme">
    <div class="container-xxl">
        <div class="footer-container d-flex flex-column flex-md-row align-items-center justify-content-center justify-content-md-between gap-2 py-3">
            <p class="mb-0 text-muted small text-center text-md-start">
                &copy; <script>document.write(new Date().getFullYear())</script>
                <a href="/" class="fw-semibold text-primary text-decoration-none">
                    {{ config('app.footer_brand', config('app.name', 'WIT. Management System')) }}
                </a>
                <span>- All rights reserved.</span>
            </p>
            <p class="mb-0 text-muted small d-none d-md-block">
                <span>Powered by</span>
                <span class="fw-semibold text-primary">ags.</span>
            </p>
        </div>
    </div>
</footer>
<!-- / Footer -->
