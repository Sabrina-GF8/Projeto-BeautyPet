import { StyleSheet, TextInput, TextInputProps } from "react-native"

export function Input({...rest }: TextInputProps) {
    return <TextInput style={styles.input} {...rest } />

}

const styles = StyleSheet.create({
    input: {
     width: "100%",
     height: 52,
     borderWidth: 1,
     borderColor: "#000000",
     backgroundColor:"#f5e8e8",
     borderRadius: 10,
     fontSize: 14,
     paddingLeft: 18,
     fontFamily: "Poppins_600SemiBold",
     marginBottom: 20,
     transform: [{ translateY: 50 }],
   
    },
    
})
