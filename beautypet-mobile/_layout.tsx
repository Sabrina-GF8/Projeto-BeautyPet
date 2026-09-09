// Importa as fontes Poppins utilizadas no aplicativo
// e a função responsável por carregá-las.
import {
    Poppins_400Regular,
    Poppins_500Medium,
    Poppins_600SemiBold,
    Poppins_700Bold,
    useFonts
} from "@expo-google-fonts/poppins";

// Importa o Stack para configurar a navegação entre as telas.
import { Stack } from "expo-router";


// Componente responsável pela configuração principal das telas do aplicativo.
export default function RootLayout() {

    // Carrega as fontes Poppins que serão utilizadas no aplicativo.
    const [fontsLoaded] = useFonts({

        // Fonte Poppins com peso regular.
        Poppins_400Regular,

        // Fonte Poppins com peso médio.
        Poppins_500Medium,

        // Fonte Poppins com peso semibold.
        Poppins_600SemiBold,

        // Fonte Poppins com peso bold.
        Poppins_700Bold,

    });


    // Verifica se as fontes terminaram de carregar.
    // Enquanto elas não estiverem carregadas, nenhuma tela é exibida.
    if (!fontsLoaded) {

        // Retorna null para aguardar o carregamento das fontes.
        return null;

    }


    // Configura a navegação principal do aplicativo.
    // headerShown: false remove o cabeçalho padrão das telas.
    return <Stack screenOptions={{ headerShown: false }}  />;
}
