library(igraph)
library(ggraph)
library(tidyverse)

# Iran data with Gregorian months
iran_months <- c("March", "April", "May", "June", "July", "August",
"September", "October", "November", "December", "January", "February")
iran_revenue <- c(16000, 12000, 10000, 20000, 19200, 18000,
14000, 11000, 13000, 12500, 13500, 14500)

# Turkey data (months are already Gregorian but need to be in English)
turkey_months <- c("January", "February", "March", "April", "May", "June",
"July", "August", "September", "October", "November", "December")
turkey_revenue <- c(60000, 64000, 68000, 48000, 44000, 40000,
84000, 80000, 72000, 56000, 52000, 68000)

# UAE data (months are already Gregorian but need to be in English)
uae_months <- c("January", "February", "March", "April", "May", "June",
"July", "August", "September", "October", "November", "December")
uae_revenue <- c(40000, 38000, 36000, 34000, 32000, 30000,
40000, 44000, 40000, 34000, 38000, 44000)

# Function to draw country revenue graph
draw_country_revenue_graph <- function(country_name, input_months, input_revenues, color) {
  # Create labels for nodes (Month - Country)
  labeled_months <- paste0(input_months, " - ", country_name)

  # Calculate revenue difference from the previous month
  revenue_diff <- c(input_revenues[1], diff(input_revenues))

  # Node data frame
  nodes <- data.frame(
    id = labeled_months,
    weight = input_revenues,
    # Use absolute difference for node size, using original revenue for the first month
    node_size = abs(revenue_diff)
  )

  # Edge data frame
  edges <- data.frame(
    from = nodes$id[1:(length(input_months)-1)],
    to   = nodes$id[2:length(input_months)],
    # Edge weight is the average revenue of connected months
    weight = (input_revenues[-length(input_revenues)] + input_revenues[-1]) / 2
  )

  # Create graph object
  graph_data <- graph_from_data_frame(d = edges, vertices = nodes, directed = TRUE)

  # Plot the graph
  ggraph(graph_data, layout = "circle") +
    geom_edge_link(aes(width = weight / 8000), color = color, alpha = 0.7) + # Edge width scaled by weight
    geom_node_point(aes(size = node_size), color = color, alpha = 0.9) +   # Node size now represents change in revenue
    geom_node_text(aes(label = name), repel = TRUE, size = 3.2, family = "Arial") + # Node labels
    theme_void() + # Minimal theme
    labs(
      title = paste("Monthly Revenue Graph -", country_name), # Plot title
      size = "Change in Monthly Revenue"                      # Updated legend title
    )
}

# Draw graphs for each country
draw_country_revenue_graph("Iran", iran_months, iran_revenue, "darkorange")
draw_country_revenue_graph("Turkey", turkey_months, turkey_revenue, "dodgerblue")
draw_country_revenue_graph("UAE", uae_months, uae_revenue, "darkgreen")
