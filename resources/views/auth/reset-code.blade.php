<x-auth-shell title="Ingresar código">
    <div class="space-y-5">
        <div class="text-center">
            <h1 class="text-3xl font-semibold tracking-tight">Ingresá el código</h1>
            <p class="mt-1 text-sm text-stone-500">Revisá tu email (también la carpeta de spam). El código tiene 6 números y vence en {{ $minutes }} minutos.</p>
        </div>

        <form method="POST" action="{{ route('password.code.verify') }}" class="space-y-5">
            @csrf
            <div class="text-center">
                <x-input name="code" label="Código de 6 números" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                         autofocus required class="code py-3 text-center text-2xl tracking-widest"/>
            </div>
            <button type="submit" class="btn btn-primary w-full py-3 text-base">Verificar código</button>
        </form>

        <form method="POST" action="{{ route('password.email') }}" class="text-center">
            @csrf
            <input type="hidden" name="login" value="{{ $login }}">
            <button type="submit" class="link text-sm">¿No te llegó? Reenviar código</button>
        </form>

        <p class="text-center text-sm"><a href="{{ route('login') }}" class="link">Volver a ingresar</a></p>
    </div>
</x-auth-shell>
