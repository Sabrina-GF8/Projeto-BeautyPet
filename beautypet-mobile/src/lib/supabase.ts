// Importa o polyfill que garante suporte a URL no ambiente React Native.
import 'react-native-url-polyfill/auto';
// Importa o AsyncStorage para o Supabase manter a sessão salva no celular.
import AsyncStorage from '@react-native-async-storage/async-storage';
// Importa a função oficial usada para criar o cliente do Supabase.
import { createClient } from '@supabase/supabase-js';

// Lê a URL pública do projeto Supabase definida no arquivo .env.
const SUPABASE_URL = process.env.EXPO_PUBLIC_SUPABASE_URL || '';
// Lê a chave pública/anon do projeto Supabase definida no arquivo .env.
const SUPABASE_ANON_KEY = process.env.EXPO_PUBLIC_SUPABASE_ANON_KEY || '';

// Interrompe cedo com uma mensagem clara caso as variáveis não tenham sido configuradas.
if (!SUPABASE_URL || !SUPABASE_ANON_KEY) {
  console.warn(
    'Beauty Pet: configure EXPO_PUBLIC_SUPABASE_URL e EXPO_PUBLIC_SUPABASE_ANON_KEY no arquivo .env.'
  );
}

// Cria uma única conexão reutilizável com o Supabase para todo o aplicativo.
export const supabase = createClient(SUPABASE_URL, SUPABASE_ANON_KEY, {
  auth: {
    // Faz o Supabase guardar a sessão no mesmo armazenamento persistente usado pelo Expo/React Native.
    storage: AsyncStorage,
    // Renova automaticamente o access token quando ele estiver perto de expirar.
    autoRefreshToken: true,
    // Mantém o usuário logado mesmo depois de fechar e abrir o aplicativo.
    persistSession: true,
    // No aplicativo nativo não precisamos interpretar sessão pela URL do navegador.
    detectSessionInUrl: false,
  },
});
