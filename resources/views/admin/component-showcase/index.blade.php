<x-app-layout>

    @section('title', 'Component Showcase | ')

    <!-- Content -->
    <div class="container-xxl flex-grow-1 container-p-y">

        <!-- Blade Components Demo -->
        <x-page-header
            :breadcrumbs="[
                ['label' => 'Home', 'url' => route('dashboard')],
                ['label' => 'Component Showcase', 'active' => true]
            ]"
            title="Component Showcase"
            subtitle="Demonstrasi semua komponen UI yang tersedia dalam sistem."
        />

        <!-- Reusable Blade Components -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Blade Components (Reusable)</h5>
            </div>
            <div class="card-body">
                <h6 class="mb-3">Alert Component</h6>
                <x-alert type="success" class="mb-3">Ini adalah contoh alert success menggunakan <code>&lt;x-alert&gt;</code>.</x-alert>
                <x-alert type="danger" class="mb-3">Ini adalah contoh alert danger.</x-alert>
                <x-alert type="warning">Ini adalah contoh alert warning.</x-alert>

                <hr class="my-4">

                <h6 class="mb-3">Form Group Component</h6>
                <div class="row">
                    <div class="col-md-6">
                        <x-form-group label="Nama Field" name="demo_field" required>
                            <input type="text" class="form-control" id="demo_field" placeholder="Input example">
                        </x-form-group>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="mb-3">Card Component</h6>
                <x-card title="Card dengan x-card component">
                    <p class="card-text mb-0">Isi card menggunakan slot. Bisa juga pakai header custom via named slot.</p>
                </x-card>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- BUTTON, LABEL, BADGE (x-button, x-badge) -->
        <!-- ============================================ -->
        <x-card title="Button, Label, Badge">
            <!-- Basic Buttons -->
            <div class="mb-4">
                <h6 class="mb-3">Basic Buttons <code>&lt;x-button&gt;</code></h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-button color="primary">Primary</x-button>
                    <x-button color="success">Success</x-button>
                    <x-button color="warning">Warning</x-button>
                    <x-button color="info">Info</x-button>
                    <x-button color="danger">Danger</x-button>
                    <x-button color="secondary">Secondary</x-button>
                    <x-button color="dark">Dark</x-button>
                    <x-button color="light">Light</x-button>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-button color="primary" size="sm">Primary Small</x-button>
                    <x-button color="success" size="sm">Success Small</x-button>
                    <x-button color="danger" size="sm">Danger Small</x-button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <x-button color="primary" size="lg">Primary Large</x-button>
                    <x-button color="success" size="lg">Success Large</x-button>
                </div>
            </div>

            <!-- Outline & Label Buttons -->
            <div class="mb-4">
                <h6 class="mb-3">Outline & Label Buttons</h6>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-button color="primary" variant="outline">Primary</x-button>
                    <x-button color="success" variant="outline">Success</x-button>
                    <x-button color="danger" variant="outline">Danger</x-button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <x-button color="primary" variant="label">Primary</x-button>
                    <x-button color="success" variant="label">Success</x-button>
                    <x-button color="danger" variant="label">Danger</x-button>
                </div>
            </div>

            <!-- Buttons with Icons -->
            <div class="mb-4">
                <h6 class="mb-3">Buttons with Icons</h6>
                <div class="d-flex flex-wrap gap-2">
                    <x-button color="primary" icon="ti-check">Primary</x-button>
                    <x-button color="success" icon="ti-check">Success</x-button>
                    <x-button color="warning" icon="ti-alert-triangle">Warning</x-button>
                    <x-button color="danger" icon="ti-x">Danger</x-button>
                </div>
            </div>

            <!-- Badges x-badge -->
            <div class="mb-4">
                <h6 class="mb-3">Badges <code>&lt;x-badge&gt;</code></h6>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    <x-badge color="primary">Primary</x-badge>
                    <x-badge color="success">Success</x-badge>
                    <x-badge color="warning">Warning</x-badge>
                    <x-badge color="danger">Danger</x-badge>
                    <x-badge color="secondary">Secondary</x-badge>
                </div>
                <div class="mb-3">
                    <p class="mb-2"><strong>Solid Badges (variant="solid"):</strong></p>
                    <div class="d-flex flex-wrap gap-2">
                        <x-badge variant="solid" color="primary">Primary Label</x-badge>
                        <x-badge variant="solid" color="success">Success Label</x-badge>
                        <x-badge variant="solid" color="danger">Danger Label</x-badge>
                    </div>
                </div>
                <div>
                    <p class="mb-2"><strong>Badges with Buttons:</strong></p>
                    <x-button color="primary">Notifications <x-badge color="light" class="ms-1">4</x-badge></x-button>
                    <x-button color="success" class="ms-2">Messages <x-badge color="light" class="ms-1">12</x-badge></x-button>
                </div>
            </div>
        </x-card>

        <!-- ============================================ -->
        <!-- CARD (x-card) -->
        <!-- ============================================ -->
        <div class="mb-4">
            <x-card title="Card Examples">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <h6 class="mb-3">Basic Card</h6>
                        <x-card>
                            <h5 class="card-title">Card Title</h5>
                            <p class="card-text mb-2">This is a basic card with some content.</p>
                            <a href="#" class="btn btn-primary">Go somewhere</a>
                        </x-card>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6 class="mb-3">Card with Header</h6>
                        <x-card title="Card Header">
                            <p class="card-text mb-0">This card has a header section and body content.</p>
                        </x-card>
                    </div>
                    <div class="col-md-6 mb-4">
                        <h6 class="mb-3">Card with Footer</h6>
                        <x-card>
                            <p class="card-text mb-0">This card has a footer section.</p>
                            <x-slot name="footer">
                                <small class="text-muted">Last updated 3 mins ago</small>
                            </x-slot>
                        </x-card>
                    </div>
                </div>
            </x-card>
        </div>

        <!-- ============================================ -->
        <!-- TABLE (x-table) -->
        <!-- ============================================ -->
        <x-card title="Table">
            <div class="mb-4">
                <h6 class="mb-3">Basic Table <code>&lt;x-table&gt;</code></h6>
                <x-table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>John Doe</td>
                                    <td>john@example.com</td>
                                    <td><x-badge color="success">Active</x-badge></td>
                                    <td>
                                        <x-button color="primary" size="sm">Edit</x-button>
                                        <x-button color="danger" size="sm">Delete</x-button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Jane Smith</td>
                                    <td>jane@example.com</td>
                                    <td><x-badge color="warning">Pending</x-badge></td>
                                    <td>
                                        <x-button color="primary" size="sm">Edit</x-button>
                                        <x-button color="danger" size="sm">Delete</x-button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Bob Johnson</td>
                                    <td>bob@example.com</td>
                                    <td><x-badge color="danger">Inactive</x-badge></td>
                                    <td>
                                        <x-button color="primary" size="sm">Edit</x-button>
                                        <x-button color="danger" size="sm">Delete</x-button>
                                    </td>
                                </tr>
                            </tbody>
                </x-table>
            </div>

            <div class="mb-4">
                <h6 class="mb-3">Bordered Table</h6>
                <x-table :bordered="true">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Product A</td>
                                    <td>$100</td>
                                    <td>50</td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Product B</td>
                                    <td>$200</td>
                                    <td>30</td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Product C</td>
                                    <td>$150</td>
                                    <td>75</td>
                                </tr>
                            </tbody>
                </x-table>
            </div>

            <div>
                <h6 class="mb-3">Striped Table</h6>
                <x-table :striped="true">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Task</th>
                                    <th>Assignee</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Design UI</td>
                                    <td>Alice</td>
                                    <td><x-badge color="success">Done</x-badge></td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Develop API</td>
                                    <td>Bob</td>
                                    <td><x-badge color="warning">In Progress</x-badge></td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Write Tests</td>
                                    <td>Charlie</td>
                                    <td><x-badge color="info">Pending</x-badge></td>
                                </tr>
                            </tbody>
                </x-table>
            </div>
        </x-card>

        <!-- ============================================ -->
        <!-- FORM INPUT -->
        <!-- ============================================ -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Form Input</h5>
            </div>
            <div class="card-body">
                <!-- Textbox dengan x-form-group -->
                <div class="mb-4">
                    <h6 class="mb-3">Textbox <code>&lt;x-form-group&gt;</code></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <x-form-group label="Basic Input" name="basicInput">
                                <input type="text" class="form-control" id="basicInput" placeholder="Enter text">
                            </x-form-group>
                        </div>
                        <div class="col-md-6">
                            <x-form-group label="Disabled Input" name="disabledInput">
                                <input type="text" class="form-control" id="disabledInput" placeholder="Disabled input" disabled>
                            </x-form-group>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="readonlyInput" class="form-label">Readonly Input</label>
                            <input type="text" class="form-control" id="readonlyInput" value="Readonly value" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="passwordInput" class="form-label">Password Input</label>
                            <input type="password" class="form-control" id="passwordInput" placeholder="Enter password">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="emailInput" class="form-label">Email Input</label>
                            <input type="email" class="form-control" id="emailInput" placeholder="name@example.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="numberInput" class="form-label">Number Input</label>
                            <input type="number" class="form-control" id="numberInput" placeholder="Enter number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="textareaInput" class="form-label">Textarea</label>
                            <textarea class="form-control" id="textareaInput" rows="3" placeholder="Enter text"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Dropdown -->
                <div class="mb-4">
                    <h6 class="mb-3">Dropdown (Select)</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="basicSelect" class="form-label">Basic Select</label>
                            <select class="form-select" id="basicSelect">
                                <option selected>Choose option...</option>
                                <option value="1">Option 1</option>
                                <option value="2">Option 2</option>
                                <option value="3">Option 3</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="multipleSelect" class="form-label">Multiple Select</label>
                            <select class="form-select" id="multipleSelect" multiple>
                                <option value="1">Option 1</option>
                                <option value="2">Option 2</option>
                                <option value="3">Option 3</option>
                                <option value="4">Option 4</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="disabledSelect" class="form-label">Disabled Select</label>
                            <select class="form-select" id="disabledSelect" disabled>
                                <option>Disabled option</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Checkbox x-checkbox -->
                <div class="mb-4">
                    <h6 class="mb-3">Checkbox <code>&lt;x-checkbox&gt;</code></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <x-checkbox id="defaultCheck1" label="Default checkbox" />
                            <x-checkbox id="defaultCheck2" label="Checked checkbox" :checked="true" />
                            <x-checkbox id="defaultCheck3" label="Disabled checkbox" :disabled="true" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Checkbox Group</label>
                            <x-checkbox name="checkboxGroup" value="option1" id="check1" label="Option 1" />
                            <x-checkbox name="checkboxGroup" value="option2" id="check2" label="Option 2" />
                            <x-checkbox name="checkboxGroup" value="option3" id="check3" label="Option 3" />
                        </div>
                    </div>
                </div>

                <!-- Radio x-radio -->
                <div class="mb-4">
                    <h6 class="mb-3">Radio <code>&lt;x-radio&gt;</code></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <x-radio name="radioDefault" id="radioDefault1" label="Default radio" />
                            <x-radio name="radioDefault" id="radioDefault2" label="Checked radio" :checked="true" />
                            <x-radio name="radioDefault" id="radioDefault3" label="Disabled radio" :disabled="true" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Radio Group</label>
                            <x-radio name="radioGroup" value="option1" id="radio1" label="Option 1" />
                            <x-radio name="radioGroup" value="option2" id="radio2" label="Option 2" />
                            <x-radio name="radioGroup" value="option3" id="radio3" label="Option 3" />
                        </div>
                    </div>
                </div>

                <!-- Input Group x-input-group -->
                <div class="mb-4">
                    <h6 class="mb-3">Input Group <code>&lt;x-input-group&gt;</code></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <x-input-group label="Input with Prefix">
                                <span class="input-group-text">@</span>
                                <input type="text" class="form-control" placeholder="Username">
                            </x-input-group>
                        </div>
                        <div class="col-md-6">
                            <x-input-group label="Input with Suffix">
                                <input type="text" class="form-control" placeholder="Amount">
                                <span class="input-group-text">.00</span>
                            </x-input-group>
                        </div>
                        <div class="col-md-6">
                            <x-input-group label="Input with Button">
                                <input type="text" class="form-control" placeholder="Search...">
                                <x-button color="primary" type="button">Search</x-button>
                            </x-input-group>
                        </div>
                        <div class="col-md-6">
                            <x-input-group label="Input with Dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#">Action</a></li>
                                    <li><a class="dropdown-item" href="#">Another action</a></li>
                                </ul>
                                <input type="text" class="form-control">
                            </x-input-group>
                        </div>
                    </div>
                </div>

                <!-- Validation -->
                <div>
                    <h6 class="mb-3">Validation</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="validInput" class="form-label">Valid Input</label>
                            <input type="text" class="form-control is-valid" id="validInput" value="Valid value">
                            <div class="valid-feedback">
                                Looks good!
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invalidInput" class="form-label">Invalid Input</label>
                            <input type="text" class="form-control is-invalid" id="invalidInput" value="Invalid value">
                            <div class="invalid-feedback">
                                Please provide a valid value.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="validSelect" class="form-label">Valid Select</label>
                            <select class="form-select is-valid" id="validSelect">
                                <option selected>Valid option</option>
                                <option value="1">Option 1</option>
                            </select>
                            <div class="valid-feedback">
                                Looks good!
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invalidSelect" class="form-label">Invalid Select</label>
                            <select class="form-select is-invalid" id="invalidSelect">
                                <option selected>Invalid option</option>
                                <option value="1">Option 1</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a valid option.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="validTextarea" class="form-label">Valid Textarea</label>
                            <textarea class="form-control is-valid" id="validTextarea" rows="3">Valid textarea content</textarea>
                            <div class="valid-feedback">
                                Looks good!
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="invalidTextarea" class="form-label">Invalid Textarea</label>
                            <textarea class="form-control is-invalid" id="invalidTextarea" rows="3">Invalid textarea content</textarea>
                            <div class="invalid-feedback">
                                Please provide a valid value.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- UI COMPONENTS -->
        <!-- ============================================ -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Komponen UI</h5>
            </div>
            <div class="card-body">
                <!-- Alerts x-alert -->
                <div class="mb-4">
                    <h6 class="mb-3">Alerts <code>&lt;x-alert&gt;</code></h6>
                    <x-alert type="primary" :dismissible="false" class="mb-2">This is a primary alert—check it out!</x-alert>
                    <x-alert type="success" :dismissible="false" class="mb-2">This is a success alert—check it out!</x-alert>
                    <x-alert type="warning" :dismissible="false" class="mb-2">This is a warning alert—check it out!</x-alert>
                    <x-alert type="danger" :dismissible="false" class="mb-2">This is a danger alert—check it out!</x-alert>
                    <x-alert type="info" :dismissible="false">This is an info alert—check it out!</x-alert>
                </div>

                <!-- Progress Bars x-progress -->
                <div class="mb-4">
                    <h6 class="mb-3">Progress <code>&lt;x-progress&gt;</code></h6>
                    <x-progress :value="25" class="mb-2" />
                    <x-progress :value="50" color="success" class="mb-2" />
                    <x-progress :value="75" color="warning" class="mb-2" />
                    <x-progress :value="100" color="danger" />
                </div>

                <!-- Spinners x-spinner -->
                <div class="mb-4">
                    <h6 class="mb-3">Spinner <code>&lt;x-spinner&gt;</code></h6>
                    <div class="d-flex flex-wrap gap-3 align-items-center">
                        <x-spinner color="primary" />
                        <x-spinner color="success" />
                        <x-spinner color="warning" />
                        <x-spinner color="danger" />
                        <x-spinner type="grow" color="primary" />
                        <x-spinner type="grow" color="success" />
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mb-4">
                    <h6 class="mb-3">Pagination</h6>
                    <nav aria-label="Page navigation example">
                        <ul class="pagination">
                            <li class="page-item"><a class="page-link" href="#">Previous</a></li>
                            <li class="page-item"><a class="page-link" href="#">1</a></li>
                            <li class="page-item active"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Next</a></li>
                        </ul>
                    </nav>
                </div>

                <!-- Breadcrumbs -->
                <div class="mb-4">
                    <h6 class="mb-3">Breadcrumbs</h6>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item"><a href="#">Library</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Data</li>
                        </ol>
                    </nav>
                </div>

                <!-- Tooltips & Popovers -->
                <div class="mb-4">
                    <h6 class="mb-3">Tooltips & Popovers</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-toggle="tooltip" data-bs-placement="top" title="Tooltip on top">
                            Tooltip on top
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="popover" data-bs-placement="top" data-bs-content="Popover content" title="Popover title">
                            Popover on top
                        </button>
                    </div>
                </div>

                <!-- Modal x-modal -->
                <div class="mb-4">
                    <h6 class="mb-3">Modal <code>&lt;x-modal&gt;</code></h6>
                    <x-button color="primary" data-bs-toggle="modal" data-bs-target="#exampleModal">
                        Launch demo modal
                    </x-button>
                    <x-modal id="exampleModal" title="Modal title">
                        This is a modal example.
                        <x-slot name="footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <x-button color="primary">Save changes</x-button>
                        </x-slot>
                    </x-modal>
                </div>

                <!-- Tabs x-tabs, x-tab-pane -->
                <div>
                    <h6 class="mb-3">Tabs <code>&lt;x-tabs&gt;</code> <code>&lt;x-tab-pane&gt;</code></h6>
                    <x-tabs :tabs="[['id' => 'home', 'label' => 'Home', 'active' => true], ['id' => 'profile', 'label' => 'Profile'], ['id' => 'contact', 'label' => 'Contact']]">
                        <x-tab-pane id="home" :active="true">
                            <p class="mt-3">This is the home tab content.</p>
                        </x-tab-pane>
                        <x-tab-pane id="profile">
                            <p class="mt-3">This is the profile tab content.</p>
                        </x-tab-pane>
                        <x-tab-pane id="contact">
                            <p class="mt-3">This is the contact tab content.</p>
                        </x-tab-pane>
                    </x-tabs>
                </div>
            </div>
        </div>

    </div>
    <!-- / Content -->

</x-app-layout>
