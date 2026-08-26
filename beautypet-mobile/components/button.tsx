import { StyleSheet, Text, TouchableOpacity, TouchableOpacityProps } from "react-native"

type ButtonProps = TouchableOpacityProps & {
    label: string
}

export function Button({label, ...rest}: ButtonProps){
   return(
    <TouchableOpacity style = {styles.container} activeOpacity={0.5} {...rest}>
       <Text style={styles.label}>{label}</Text>

    </TouchableOpacity>
   )

}

const styles = StyleSheet.create({
    container:{
    width: "100%",
    height: 48,
    backgroundColor:"#07219e",
    alignItems: "center",
    justifyContent: "center",
    borderRadius: 10,
    marginTop: 65,
    },
    label: {
     color: "#FFFFFF",
     fontSize: 16,
     fontWeight: "600",

    },
})
