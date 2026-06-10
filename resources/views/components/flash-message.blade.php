@if (session('success'))
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-4">
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-md text-sm">
            {{ session('success') }}
        </div>
    </div>
@endif
